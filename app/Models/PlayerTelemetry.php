<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerTelemetry extends Model
{
    public $timestamps = false; // Kita pakai recorded_at manual

    protected $fillable = [
        'screen_id', 'cpu_usage', 'memory_usage', 
        'temperature', 'uptime_sec', 'app_version', 'recorded_at'
    ];
    
    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}