<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'file_name' => $this->file_name,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'size_mb' => round($this->size / 1024 / 1024, 2),
            'duration_sec' => $this->duration,
            'status' => $this->status, // processing status
            'moderation' => [
                'status' => $this->moderation_status,
                'notes' => $this->moderation_notes,
            ],
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}