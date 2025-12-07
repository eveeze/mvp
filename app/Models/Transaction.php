<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\TransactionType;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'amount',
        'type', // 'credit', 'debit'
        'description',
        'reference_id',
        'reference_type',
        'balance_after'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'type'=> TransactionType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Relasi Polimorfik (Bisa ke Deposit atau Campaign)
     */
    public function reference()
    {
        return $this->morphTo();
    }
}