<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'balance_after' => (float) $this->balance_after,
            'date' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}