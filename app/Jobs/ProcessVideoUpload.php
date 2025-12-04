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

    // Timeout 20 menit karena proses video HLS cukup berat
    public $timeout = 1200; 

    public function __construct(protected Media $media) {}

    public function handle(): void
    {
        $this->media->update(['status' => 'processing']);

        // 1. Siapkan Folder Temporary di Server (Local)
        // Kita butuh folder khusus per video agar file .ts tidak tercampur
        $tempDir = storage_path('app/temp/' . $this->media->id);
        
        // Pastikan folder bersih/baru
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }
        File::makeDirectory($tempDir, 0777, true);

        $localRawPath = $tempDir . '/raw_input.mp4';
        $hlsPlaylistName = 'playlist.m3u8';
        $localPlaylistPath = $tempDir . '/' . $hlsPlaylistName;

        try {
            // 2. Download File Mentah dari Storage ke Temp Folder
            File::put($localRawPath, Storage::get($this->media->path_original));

            // 3. Ambil Durasi Video (Detik) menggunakan ffprobe
            $durationProcess = Process::fromShellCommandline("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$localRawPath}");
            $durationProcess->run();
            $duration = (int) floatval($durationProcess->getOutput());

            // 4. Jalankan FFmpeg untuk Konversi ke HLS
            // -hls_time 10         : Setiap pecahan video berdurasi 10 detik
            // -hls_list_size 0     : Simpan semua list segmen (mode VOD/Video on Demand)
            // -hls_segment_filename: Pola nama file pecahan (segment_001.ts, segment_002.ts, dst)
            
            $convertCommand = [
                'ffmpeg',
                '-y',                // Overwrite output
                '-i', $localRawPath, // Input file
                '-c:v', 'libx264',   // Video Codec (H.264)
                '-crf', '23',        // Kualitas Visual (makin kecil makin bagus, standar 23)
                '-preset', 'fast',   // Kecepatan encoding
                '-c:a', 'aac',       // Audio Codec
                '-b:a', '128k',      // Bitrate Audio
                '-g', '60',          // Keyframe interval (penting utk streaming lancar)
                '-hls_time', '10',   
                '-hls_list_size', '0',
                '-hls_segment_filename', $tempDir . '/segment_%03d.ts',
                '-f', 'hls',         // Format output HLS
                $localPlaylistPath
            ];

            $process = new Process($convertCommand);
            $process->setTimeout(1200); // Samakan dengan timeout job
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('FFmpeg Failed: ' . $process->getErrorOutput());
            }

            // 5. Upload Hasil (Folder HLS) ke S3
            // Kita simpan di folder S3: videos/hls/{id}/
            $s3Folder = 'videos/hls/' . $this->media->id;
            
            $files = File::files($tempDir);
            foreach ($files as $file) {
                // Skip file raw input, kita cuma mau upload hasil convert (.m3u8 & .ts)
                if ($file->getFilename() === 'raw_input.mp4') continue;

                Storage::disk('s3')->putFileAs(
                    $s3Folder,
                    $file,
                    $file->getFilename(),
                    'public' // Set visibilitas public agar bisa di-stream player
                );
            }

            // Path final adalah lokasi file .m3u8 utama
            $finalPath = $s3Folder . '/' . $hlsPlaylistName;

            // 6. Update Database & Bersihkan File Mentah
            // Hapus file raw upload user dari S3 untuk hemat biaya (opsional)
            if ($this->media->path_original) {
                Storage::delete($this->media->path_original); 
            }

            $this->media->update([
                'status' => 'completed',
                'path_optimized' => $finalPath,
                'path_original' => null, // File raw sudah dihapus
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            $this->media->update(['status' => 'failed']);
            \Log::error("HLS Processing Error (Media ID {$this->media->id}): " . $e->getMessage());
        } finally {
            // 7. Bersihkan Folder Temp di Server
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }
}