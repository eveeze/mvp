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
        'type', // Baru
        'file_name', 
        'mime_type', 
        'size', 
        'duration',
        'path_original', 
        'path_optimized', 
        'thumbnail_path', // Baru
        'status', // processing status
        'moderation_status', // Baru: pending, approved, rejected
        'moderation_notes' // Baru
    ];

    /**
     * URL Utama (Video/Image Optimized)
     */
    public function getUrlAttribute()
    {
        if ($this->status === 'completed' && $this->path_optimized) {
            return Storage::disk('s3')->url($this->path_optimized);
        }
        return null;
    }

    /**
     * URL Thumbnail (Untuk CMS)
     */
    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail_path) {
            return Storage::disk('s3')->url($this->thumbnail_path);
        }
        return null; // Bisa diganti dengan URL placeholder default
    }
}