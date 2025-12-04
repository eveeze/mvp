<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File; // Ini Facade untuk operasi folder
use Symfony\Component\Process\Process;

class ProcessVideoUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 Jam

    public function __construct(protected Media $media) {}

    public function handle(): void
    {
        $this->media->update(['status' => 'processing']);

        // 1. Setup Folder Temp Unik
        $tempId = $this->media->id . '_' . uniqid();
        $tempDir = storage_path('app/temp/' . $tempId);
        
        $this->ensureDir($tempDir);
        foreach (['480p', '720p', '1080p'] as $q) {
            $this->ensureDir($tempDir . '/' . $q);
        }

        $localRawPath = $tempDir . '/input.mp4';
        
        try {
            // 2. Download File
            $disk = $this->getDiskFromPath($this->media->path_original);
            $srcStream = Storage::disk($disk)->readStream($this->media->path_original);
            $destStream = fopen($localRawPath, 'w');
            stream_copy_to_stream($srcStream, $destStream);
            fclose($srcStream);
            fclose($destStream);

            // 3. Analisa File
            $probeCmd = [
                'ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type:format=duration', 
                '-of', 'json', $localRawPath
            ];
            $probeProcess = new Process($probeCmd);
            $probeProcess->run();
            $probeOutput = json_decode($probeProcess->getOutput(), true);
            $duration = (int) ($probeOutput['format']['duration'] ?? 0);
            
            $hasAudio = false;
            foreach ($probeOutput['streams'] ?? [] as $stream) {
                if (($stream['codec_type'] ?? '') === 'audio') {
                    $hasAudio = true;
                    break;
                }
            }

            // 4. Susun Command FFmpeg
            $cmd = ['ffmpeg', '-y', '-i', $localRawPath];
            $varStreamMap = [];

            // 480p
            $cmd = array_merge($cmd, ['-map', '0:v:0', '-s:v:0', '854x480', '-c:v:0', 'libx264', '-b:v:0', '800k', '-preset', 'veryfast']);
            if ($hasAudio) {
                $cmd = array_merge($cmd, ['-map', '0:a:0', '-c:a:0', 'aac', '-b:a:0', '96k', '-ac', '2', '-ar', '44100']);
                $varStreamMap[] = "v:0,a:0,name:480p";
            } else {
                $varStreamMap[] = "v:0,name:480p";
            }

            // 720p
            $cmd = array_merge($cmd, ['-map', '0:v:0', '-s:v:1', '1280x720', '-c:v:1', 'libx264', '-b:v:1', '2500k', '-preset', 'veryfast']);
            if ($hasAudio) {
                $cmd = array_merge($cmd, ['-map', '0:a:0', '-c:a:1', 'aac', '-b:a:1', '128k', '-ac', '2', '-ar', '44100']);
                $varStreamMap[] = "v:1,a:1,name:720p";
            } else {
                $varStreamMap[] = "v:1,name:720p";
            }

            // 1080p
            $cmd = array_merge($cmd, ['-map', '0:v:0', '-s:v:2', '1920x1080', '-c:v:2', 'libx264', '-b:v:2', '5000k', '-preset', 'veryfast']);
            if ($hasAudio) {
                $cmd = array_merge($cmd, ['-map', '0:a:0', '-c:a:2', 'aac', '-b:a:2', '192k', '-ac', '2', '-ar', '44100']);
                $varStreamMap[] = "v:2,a:2,name:1080p";
            } else {
                $varStreamMap[] = "v:2,name:1080p";
            }

            $cmd = array_merge($cmd, [
                '-f', 'hls',
                '-var_stream_map', implode(' ', $varStreamMap),
                '-master_pl_name', 'master.m3u8',
                '-hls_time', '6',
                '-hls_playlist_type', 'vod',
                '-hls_segment_filename', $tempDir . '/%v/segment_%03d.ts',
                $tempDir . '/%v/playlist.m3u8'
            ]);

            $process = new Process($cmd);
            $process->setTimeout(3600);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('FFmpeg Failed: ' . $process->getErrorOutput());
            }

            // 5. Upload ke S3
            $s3Base = 'hls/' . $this->media->id;
            
            // [FIX] Gunakan \Illuminate\Http\File untuk upload file fisik
            Storage::disk('s3')->putFileAs(
                $s3Base, 
                new \Illuminate\Http\File($tempDir . '/master.m3u8'), // <-- FIX DISINI
                'master.m3u8', 
                'public'
            );

            // Upload Segments
            foreach (['480p', '720p', '1080p'] as $quality) {
                if (!File::exists($tempDir . '/' . $quality)) continue;
                foreach (File::files($tempDir . '/' . $quality) as $file) {
                    Storage::disk('s3')->putFileAs(
                        $s3Base . '/' . $quality, 
                        new \Illuminate\Http\File($file->getPathname()), // <-- FIX DISINI JUGA
                        $file->getFilename(), 
                        'public'
                    );
                }
            }

            // 6. Update DB
            $this->media->update([
                'status' => 'completed',
                'path_optimized' => $s3Base . '/master.m3u8',
                'path_original' => null,
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            $this->media->update(['status' => 'failed']);
            \Log::error("HLS Process ID {$this->media->id}: " . $e->getMessage());
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    protected function ensureDir($path) {
        if (!File::exists($path)) File::makeDirectory($path, 0777, true);
    }

    protected function getDiskFromPath($path) {
        return Storage::disk('s3')->exists($path) ? 's3' : 'local';
    }
}