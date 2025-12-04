<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Jobs\ProcessVideoUpload;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Upload Video Iklan (Advertiser).
     */
    public function store(Request $request)
    {
        $request->validate([
            // Validasi file: Wajib video (mp4/mov/avi), Max 100MB
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:102400',
        ]);

        $file = $request->file('video');
        $user = $request->user();

        // 1. Simpan file 'mentah' ke folder temporary (bisa di local atau s3)
        // Kita simpan sebagai 'temp' agar nanti dihapus oleh Job setelah sukses convert
        $path = $file->store('videos/temp', 'local'); 
        // Catatan: Jika server production menggunakan multi-server, gunakan disk 's3' untuk temp storage juga.

        // 2. Buat Record DB
        $media = Media::create([
            'user_id'       => $user->id,
            'file_name'     => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'path_original' => $path,
            'status'        => 'pending' // Status awal: Pending
        ]);

        // 3. Dispatch Job (Background Process)
        // Video akan diproses menjadi HLS di latar belakang
        ProcessVideoUpload::dispatch($media);

        return response()->json([
            'status' => 'success',
            'message' => 'Video uploaded. Processing started in background.',
            'data'    => $media
        ], 201);
    }

    /**
     * List Video milik User.
     */
    public function index(Request $request)
    {
        $medias = Media::where('user_id', $request->user()->id)
                    ->orderBy('created_at', 'desc')
                    ->paginate(10); 

        // Append URL attribute ke JSON result
        // Agar frontend langsung dapat URL .m3u8
        $medias->getCollection()->transform(function ($media) {
            $media->url = $media->url; // Trigger accessor getUrlAttribute
            return $media;
        });

        return response()->json([
            'status' => 'success',
            'data' => $medias
        ]);
    }
}