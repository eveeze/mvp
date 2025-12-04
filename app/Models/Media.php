<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 
        'file_name', 
        'mime_type', 
        'size', 
        'duration',
        'path_original', 
        'path_optimized', 
        'status'
    ];

    /**
     * Helper untuk mendapatkan URL Streaming.
     * Otomatis mengarah ke file .m3u8 di S3 jika status completed.
     */
    public function getUrlAttribute()
    {
        if ($this->status === 'completed' && $this->path_optimized) {
            // Mengembalikan URL publik S3 ke file .m3u8
            return Storage::disk('s3')->url($this->path_optimized);
        }
        
        return null;
    }
}