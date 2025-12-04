<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Jobs\ProcessVideoUpload;
use App\Jobs\ProcessImageUpload;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400|mimetypes:video/mp4,video/quicktime,image/jpeg,image/png,image/webp',
        ]);

        $file = $request->file('file');
        $user = $request->user();
        $mimeType = $file->getMimeType();
        
        // Deteksi Tipe
        $type = str_starts_with($mimeType, 'video') ? 'video' : 'image';
        $folder = $type === 'video' ? 'videos/temp' : 'images/temp';

        // Upload Raw
        $path = $file->store($folder, 's3');

        $media = Media::create([
            'user_id'       => $user->id,
            'type'          => $type,
            'file_name'     => $file->getClientOriginalName(),
            'mime_type'     => $mimeType,
            'size'          => $file->getSize(),
            'path_original' => $path,
            'status'        => 'pending',
            'moderation_status' => 'pending', // Default pending
        ]);

        // Dispatch Job Sesuai Tipe
        if ($type === 'video') {
            ProcessVideoUpload::dispatch($media);
        } else {
            ProcessImageUpload::dispatch($media);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Media uploaded.',
            'data'    => $media
        ], 201);
    }

    public function index(Request $request)
    {
        $medias = Media::where('user_id', $request->user()->id)
                    ->latest()
                    ->paginate(10);

        // Append helper URLs
        $medias->getCollection()->transform(function ($media) {
            $media->url = $media->url;
            $media->thumbnail = $media->thumbnail_url;
            return $media;
        });

        return response()->json(['status' => 'success', 'data' => $medias]);
    }
}