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

    // Timeout 30 menit (aman untuk video besar/lambat)
    public $timeout = 1800; 

    public function __construct(protected Media $media) {}

    public function handle(): void
    {
        $this->media->update(['status' => 'processing']);

        // 1. Setup Folder Temp
        $tempId = $this->media->id . '_' . uniqid();
        $tempDir = storage_path('app/temp/' . $tempId);
        
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0777, true);
        }

        $localRawPath = $tempDir . '/input.mp4';
        $playlistName = 'playlist.m3u8';
        $localPlaylistPath = $tempDir . '/' . $playlistName;

        try {
            // 2. Download File dari Storage ke Temp
            File::put($localRawPath, Storage::get($this->media->path_original));

            // 3. Ambil Durasi (ffprobe)
            $probeCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$localRawPath}";
            $probeProcess = Process::fromShellCommandline($probeCmd);
            $probeProcess->run();
            $duration = (int) floatval($probeProcess->getOutput());

            // 4. Konversi ke HLS (ffmpeg)
            // -hls_time 10: Potongan 10 detik
            // -hls_list_size 0: Simpan semua segmen
            // -c:v libx264 -preset veryfast: Encoding cepat kompatibel
            $ffmpegCmd = [
                'ffmpeg', '-y', '-i', $localRawPath,
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                '-c:a', 'aac', '-b:a', '128k',
                '-hls_time', '10', '-hls_playlist_type', 'vod', 
                '-hls_segment_filename', $tempDir . '/segment_%03d.ts',
                $localPlaylistPath
            ];

            $process = new Process($ffmpegCmd);
            $process->setTimeout(1800);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('FFmpeg failed: ' . $process->getErrorOutput());
            }

            // 5. Upload ke Storage (Public)
            $s3Folder = 'hls/' . $this->media->id;
            $files = File::files($tempDir);

            foreach ($files as $file) {
                if ($file->getFilename() === 'input.mp4') continue;
                
                // Gunakan putFileAs agar Content-Type otomatis terdeteksi
                Storage::putFileAs(
                    $s3Folder, 
                    $file, 
                    $file->getFilename(), 
                    'public'
                );
            }

            // 6. Cleanup & Update DB
            // Hapus file raw original untuk hemat space (Opsional)
            Storage::delete($this->media->path_original);

            $this->media->update([
                'status' => 'completed',
                'path_optimized' => $s3Folder . '/' . $playlistName,
                'path_original' => null,
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            $this->media->update(['status' => 'failed']);
            \Log::error("Video Processing Error ID {$this->media->id}: " . $e->getMessage());
            // Jangan throw exception agar queue worker tidak restart terus menerus untuk file corrupt
        } finally {
            // Hapus folder temp lokal
            File::deleteDirectory($tempDir);
        }
    }
}