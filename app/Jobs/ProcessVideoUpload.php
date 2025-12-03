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

    public $timeout = 600; // 10 menit timeout karena render video lama

    public function __construct(protected Media $media) {}

    public function handle(): void
    {
        $this->media->update(['status' => 'processing']);

        try {
            // 1. Download file asli dari Storage ke Local Temp (container)
            $tempLocalPath = storage_path('app/temp/' . $this->media->id . '_raw.mp4');
            $hlsOutputDir  = storage_path('app/public/hls/' . $this->media->id);
            
            // Pastikan direktori ada
            if (!File::exists(dirname($tempLocalPath))) File::makeDirectory(dirname($tempLocalPath), 0755, true);
            if (!File::exists($hlsOutputDir)) File::makeDirectory($hlsOutputDir, 0755, true);

            // Copy dari S3/Local Storage ke temp folder processing
            file_put_contents($tempLocalPath, Storage::get($this->media->path_original));

            // 2. Jalankan FFmpeg Command
            // Convert ke HLS (.m3u8) dengan segmen 10 detik
            $playlistPath = $hlsOutputDir . '/playlist.m3u8';
            
            $command = [
                'ffmpeg',
                '-i', $tempLocalPath,
                '-profile:v', 'baseline', // Profile ringan kompatibel banyak device
                '-level', '3.0',
                '-start_number', '0',
                '-hls_time', '10',        // Durasi per pecahan (segment)
                '-hls_list_size', '0',    // 0 = simpan semua list (VOD), bukan live
                '-f', 'hls',
                $playlistPath
            ];

            $process = new Process($command);
            $process->setTimeout(600);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('FFmpeg failed: ' . $process->getErrorOutput());
            }

            // 3. Upload Hasil (Folder HLS) kembali ke MinIO (S3)
            $files = File::allFiles($hlsOutputDir);
            $s3BasePath = 'hls/' . $this->media->id;

            foreach ($files as $file) {
                // Upload .m3u8 dan .ts files
                Storage::disk('s3')->putFileAs(
                    $s3BasePath, 
                    $file, 
                    $file->getFilename(), 
                    'public' // Set visibility public
                );
            }

            // 4. Update Database
            $this->media->update([
                'status'   => 'completed',
                'path_hls' => $s3BasePath . '/playlist.m3u8'
            ]);

            // 5. Cleanup File Sampah di Container
            File::delete($tempLocalPath);
            File::deleteDirectory($hlsOutputDir);

        } catch (\Exception $e) {
            $this->media->update(['status' => 'failed']);
            \Log::error("Video Processing Failed ID {$this->media->id}: " . $e->getMessage());
            throw $e; // Retry job if config allows
        }
    }
}