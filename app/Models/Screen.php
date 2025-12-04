<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'name',
        'code',
        'location',
        'resolution_width',
        'resolution_height',
        'orientation',
        'price_per_play',     
        'max_plays_per_day',
        'max_duration_sec',
        'is_active',          
        'is_online',          
        'allowed_categories',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'is_online'          => 'boolean',
        'allowed_categories' => 'array',
        'price_per_play'     => 'decimal:2', 
        'resolution_width'   => 'integer',
        'resolution_height'  => 'integer',
        'max_plays_per_day'  => 'integer',
        'max_duration_sec'   => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
    
    public function campaignItems()
    {
        return $this->hasMany(CampaignItem::class);
    }
}