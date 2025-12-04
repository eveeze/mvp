<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 
        'name', 
        'start_date', 
        'end_date', 
        'total_cost', 
        'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'total_cost' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(CampaignItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}