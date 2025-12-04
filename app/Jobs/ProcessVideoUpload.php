<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ProcessVideoUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Timeout 30 menit
    public $timeout = 1800;

    public function __construct(protected Media $media) {}

    public function handle(): void
    {
        $this->media->update(['status' => 'processing']);

        // Folder Temporary Lokal (Bukan S3)
        $tempId = $this->media->id . '_' . uniqid();
        $tempDir = storage_path('app/temp/' . $tempId);
        
        // Pastikan direktori temp ada
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0777, true);
        }

        $localRawPath = $tempDir . '/input.mp4';
        $playlistName = 'playlist.m3u8';
        $localPlaylistPath = $tempDir . '/' . $playlistName;

        try {
            // 1. Download file Raw dari S3/Local ke Folder Temp Server
            // Menggunakan stream copy agar hemat memori
            $srcStream = Storage::disk($this->getDiskFromPath($this->media->path_original))
                            ->readStream($this->media->path_original);
            $destStream = fopen($localRawPath, 'w');
            stream_copy_to_stream($srcStream, $destStream);
            fclose($srcStream);
            fclose($destStream);

            // 2. Ambil Durasi Video
            $probeCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$localRawPath}";
            $probeProcess = Process::fromShellCommandline($probeCmd);
            $probeProcess->run();
            $duration = (int) floatval($probeProcess->getOutput());

            // 3. Konversi ke HLS (FFmpeg)
            $ffmpegCmd = [
                'ffmpeg', '-y', '-i', $localRawPath,
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                '-c:a', 'aac', '-b:a', '128k',
                '-hls_time', '10', 
                '-hls_playlist_type', 'vod', 
                '-hls_segment_filename', $tempDir . '/segment_%03d.ts',
                $localPlaylistPath
            ];

            $process = new Process($ffmpegCmd);
            $process->setTimeout(1800);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('FFmpeg failed: ' . $process->getErrorOutput());
            }

            // 4. Upload ke MinIO (S3) - Folder Public
            $s3Folder = 'hls/' . $this->media->id;
            $files = File::files($tempDir);

            foreach ($files as $file) {
                if ($file->getFilename() === 'input.mp4') continue;

                // Explicitly use 's3' disk
                Storage::disk('s3')->putFileAs(
                    $s3Folder, 
                    $file, 
                    $file->getFilename(), 
                    'public'
                );
            }

            // 5. Update Database
            $this->media->update([
                'status' => 'completed',
                // URL lengkap ke playlist
                'path_optimized' => $s3Folder . '/' . $playlistName, 
                // Opsional: path_original bisa diset null jika file raw dihapus
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            $this->media->update(['status' => 'failed']);
            \Log::error("Video Processing Error [ID: {$this->media->id}]: " . $e->getMessage());
        } finally {
            // Cleanup folder temp lokal
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Helper: Cek file raw ada di disk mana (s3 atau local)
     */
    protected function getDiskFromPath($path)
    {
        return Storage::disk('s3')->exists($path) ? 's3' : 'local';
    }
}