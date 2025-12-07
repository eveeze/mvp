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

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 Menit

    public function __construct(protected Media $media) {}

    public function handle(): void
    {
        $this->media->update(['status' => 'processing']);

        $tempId = $this->media->id . '_' . uniqid();
        $tempDir = storage_path('app/temp/' . $tempId);
        
        if (!File::exists($tempDir)) File::makeDirectory($tempDir, 0777, true);

        $localRawPath = $tempDir . '/input_image';
        $localWebpPath = $tempDir . '/optimized.webp';
        $localThumbPath = $tempDir . '/thumbnail.webp';

        try {
            // 1. Download File
            $disk = $this->getDiskFromPath($this->media->path_original);
            $srcStream = Storage::disk($disk)->readStream($this->media->path_original);
            $destStream = fopen($localRawPath, 'w');
            stream_copy_to_stream($srcStream, $destStream);
            fclose($srcStream);
            fclose($destStream);

            // 2. Convert ke WebP (High Quality)
            $process = new Process([
                'ffmpeg', '-y', '-i', $localRawPath,
                '-c:v', 'libwebp', '-q:v', '85', 
                $localWebpPath
            ]);
            $process->run();
            if (!$process->isSuccessful()) throw new \Exception('WebP Conversion Failed: ' . $process->getErrorOutput());

            // 3. Generate Thumbnail (Small)
            $processThumb = new Process([
                'ffmpeg', '-y', '-i', $localRawPath,
                '-vf', 'scale=320:-1',
                '-c:v', 'libwebp', '-q:v', '70', 
                $localThumbPath
            ]);
            $processThumb->run();

            // 4. Upload ke S3
            $s3Folder = 'images/' . $this->media->id;
            
            Storage::disk('s3')->putFileAs($s3Folder, new \Illuminate\Http\File($localWebpPath), 'optimized.webp', 'public');
            Storage::disk('s3')->putFileAs($s3Folder, new \Illuminate\Http\File($localThumbPath), 'thumbnail.webp', 'public');

            // 5. Update DB
            $this->media->update([
                'status' => 'completed',
                'path_optimized' => $s3Folder . '/optimized.webp',
                'thumbnail_path' => $s3Folder . '/thumbnail.webp',
                'path_original' => null, // Hapus raw
                'duration' => 10 // Durasi default gambar 10 detik
            ]);

        } catch (\Exception $e) {
            $this->media->update(['status' => 'failed']);
            \Log::error("Image Process Error ID {$this->media->id}: " . $e->getMessage());
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    protected function getDiskFromPath($path)
    {
        return Storage::disk('s3')->exists($path) ? 's3' : 'local';
    }
}