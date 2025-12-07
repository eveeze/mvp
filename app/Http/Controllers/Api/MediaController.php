<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Jobs\ProcessVideoUpload;
use App\Jobs\ProcessImageUpload;
use App\Enums\MediaType;
use App\Enums\ModerationStatus;
use App\Http\Resources\MediaResource; // [PENTING] Import Resource
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Upload Media (Video/Image).
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400|mimetypes:video/mp4,video/quicktime,image/jpeg,image/png,image/webp',
        ]);

        $file = $request->file('file');
        $user = $request->user();
        $mimeType = $file->getMimeType();
        
        // Deteksi Tipe (Video/Image)
        $type = str_starts_with($mimeType, 'video') ? MediaType::VIDEO : MediaType::IMAGE;
        $folder = $type === MediaType::VIDEO ? 'videos/temp' : 'images/temp';

        // Upload Raw (Private)
        $path = $file->store($folder, ['disk' => 's3', 'visibility' => 'private']);

        // Simpan ke Database
        $media = Media::create([
            'user_id'       => $user->id,
            'type'          => $type,
            'file_name'     => $file->getClientOriginalName(),
            'mime_type'     => $mimeType,
            'size'          => $file->getSize(),
            'path_original' => $path,
            'status'        => 'pending', // Status processing
            'moderation_status' => ModerationStatus::PENDING, // Status admin review
        ]);

        // Dispatch Job di Background
        if ($type === MediaType::VIDEO) {
            ProcessVideoUpload::dispatch($media);
        } else {
            ProcessImageUpload::dispatch($media);
        }

        // [REFACTOR] Return menggunakan API Resource
        // Ini memastikan format JSON sesuai dengan standar yang Anda buat di MediaResource
        return new MediaResource($media);
    }

    /**
     * List Media User.
     */
    public function index(Request $request)
    {
        $medias = Media::where('user_id', $request->user()->id)
                    ->latest()
                    ->paginate(10);
        
        // [REFACTOR] Return Collection Resource
        // Tidak perlu loop/transform manual lagi, Laravel otomatis memetakan ke MediaResource
        return MediaResource::collection($medias);
    }
}