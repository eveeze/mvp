<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Screen extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'code',
        'location',
        'resolution_width',
        'resolution_height',
        'orientation',
        'is_online',
        'allowed_categories',
    ];

    protected $casts = [
        'is_online'          => 'boolean',
        'allowed_categories' => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
