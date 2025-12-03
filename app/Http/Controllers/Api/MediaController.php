<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Jobs\ProcessVideoUpload;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4|max:51200', // Max 50MB (sesuaikan)
        ]);

        $file = $request->file('video');
        $user = $request->user();

        // 1. Simpan file asli (Raw) ke folder sementara/raw
        // Kita simpan dulu di disk 'local' atau 's3' folder 'raw'
        $path = $file->store('raw_uploads', 'local'); 

        // 2. Buat Record DB
        $media = Media::create([
            'user_id'       => $user->id,
            'file_name'     => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'path_original' => $path, // Path di storage
            'status'        => 'pending'
        ]);

        // 3. Dispatch Job untuk konversi (Async)
        ProcessVideoUpload::dispatch($media);

        return response()->json([
            'message' => 'Video uploaded and processing started.',
            'data'    => $media
        ], 201);
    }

    public function index(Request $request)
    {
        // List video milik user
        $medias = Media::where('user_id', $request->user()->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json(['data' => $medias]);
    }
}