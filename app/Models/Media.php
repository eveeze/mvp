<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'file_name', 'mime_type', 'size', 
        'path_original', 'path_hls', 'status'
    ];

    // Helper untuk generate Full URL MinIO
    public function getUrlAttribute()
    {
        if ($this->status !== 'completed' || !$this->path_hls) {
            return null;
        }
        // Menggunakan Storage facade untuk get url
        return \Illuminate\Support\Facades\Storage::disk('s3')->url($this->path_hls);
    }
}