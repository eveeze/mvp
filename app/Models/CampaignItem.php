<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 
        'screen_id', 
        'media_id', 
        'plays_per_day', 
        'price_per_play'
    ];

    protected $casts = [
        'price_per_play' => 'decimal:2',
        'plays_per_day' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function screen()
    {
        return $this->belongsTo(Screen::class);
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}