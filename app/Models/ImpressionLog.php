<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpressionLog extends Model
{
    protected $fillable = [
        'screen_id', 'media_id', 'played_at', 'duration_sec'
    ];

    protected $casts = [
        'played_at' => 'datetime',
    ];

    public function screen() { return $this->belongsTo(Screen::class); }
    public function media() { return $this->belongsTo(Media::class); }
}