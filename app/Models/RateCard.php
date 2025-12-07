<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateCard extends Model
{
    protected $fillable = [
        'hotel_star_rating',
        'duration_days',
        'base_price',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'hotel_star_rating' => 'integer',
        'duration_days' => 'integer',
    ];
}