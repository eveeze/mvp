<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'amount',
        'admin_fee',
        'total_amount',
        'status',
        'snap_token',
        'payment_details'
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'admin_fee'       => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'payment_details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}