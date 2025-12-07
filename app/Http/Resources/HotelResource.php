<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'contact_phone' => $this->contact_phone,
            'star_rating' => $this->star_rating,
            'price_override' => $this->price_override ? (float) $this->price_override : null,
            'is_active' => $this->is_active,
            'screens_count' => $this->whenCounted('screens'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}