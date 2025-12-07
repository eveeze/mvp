<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'hotel' => new HotelResource($this->whenLoaded('hotel')), // Include Hotel jika diload
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'resolution' => $this->resolution_width . 'x' . $this->resolution_height,
            'orientation' => $this->orientation,
            'price_per_play' => $this->price_per_play ? (float) $this->price_per_play : null,
            'max_plays_per_day' => $this->max_plays_per_day,
            'status' => [
                'is_active' => $this->is_active,
                'is_online' => $this->is_online,
                'last_seen_at' => $this->last_seen_at?->diffForHumans(),
            ],
        ];
    }
}