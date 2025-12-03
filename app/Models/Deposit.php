<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $fillable = [
        'wallet_id',
        'amount',
        'status',
        'payment_method', // Nullable
        'order_id',       // Midtrans
        'snap_token',     // Midtrans
        'payment_type',   // Midtrans
        'raw_response'    // Midtrans
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}