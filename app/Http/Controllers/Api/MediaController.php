<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Jobs\ProcessVideoUpload;
use App\Jobs\ProcessImageUpload;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Upload Media (Video/Image).
     * File raw disimpan private, hasil proses (HLS/WebP) akan public.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400|mimetypes:video/mp4,video/quicktime,image/jpeg,image/png,image/webp',
        ]);

        $file = $request->file('file');
        $user = $request->user();
        $mimeType = $file->getMimeType();
        
        // Deteksi Tipe Media
        $type = str_starts_with($mimeType, 'video') ? 'video' : 'image';
        $folder = $type === 'video' ? 'videos/temp' : 'images/temp';

        // [SECURE STORAGE] 
        // Simpan file mentah sebagai PRIVATE agar tidak bisa diakses publik
        // Hanya worker yang bisa membacanya nanti via Storage::disk('s3')->readStream()
        $path = $file->store($folder, ['disk' => 's3', 'visibility' => 'private']);

        $media = Media::create([
            'user_id'       => $user->id,
            'type'          => $type,
            'file_name'     => $file->getClientOriginalName(),
            'mime_type'     => $mimeType,
            'size'          => $file->getSize(),
            'path_original' => $path,
            'status'        => 'pending',
            'moderation_status' => 'pending',
        ]);

        // Dispatch Job Sesuai Tipe
        if ($type === 'video') {
            ProcessVideoUpload::dispatch($media);
        } else {
            ProcessImageUpload::dispatch($media);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Media uploaded successfully.',
            'data'    => $media
        ], 201);
    }

    /**
     * List Media User.
     */
    public function index(Request $request)
    {
        $medias = Media::where('user_id', $request->user()->id)
                    ->latest()
                    ->paginate(10);

        // Transform collection untuk memunculkan Accessor URL
        $medias->getCollection()->transform(function ($media) {
            // [FIX] Gunakan append() untuk memunculkan accessor 'url' dan 'thumbnail_url'
            // Ini menghilangkan warning "Assignment to same variable"
            $media->append(['url', 'thumbnail_url']);
            
            // Mapping alias 'thumbnail' (opsional, untuk kompatibilitas frontend)
            $media->thumbnail = $media->thumbnail_url;
            
            return $media;
        });

        return response()->json([
            'status' => 'success',
            'data' => $medias
        ]);
    }
}