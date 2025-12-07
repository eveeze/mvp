<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'screen' => new ScreenResource($this->whenLoaded('screen')),
            'media' => new MediaResource($this->whenLoaded('media')),
            'plays_per_day' => $this->plays_per_day,
            'price_snapshot' => (float) $this->price_per_play,
        ];
    }
}