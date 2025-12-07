<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'advertiser' => $this->whenLoaded('user', fn() => $this->user->name),
            'dates' => [
                'start' => $this->start_date->toDateString(),
                'end' => $this->end_date->toDateString(),
            ],
            'total_cost' => (float) $this->total_cost,
            'status' => $this->status,
            'moderation_status' => $this->moderation_status,
            // Jika items di-load, tampilkan detail
            'items' => CampaignItemResource::collection($this->whenLoaded('items')), 
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}