<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Enums\MediaType;
use App\Enums\ModerationStatus;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 
        'type', 
        'file_name', 
        'mime_type', 
        'size', 
        'duration',
        'path_original', 
        'path_optimized', 
        'thumbnail_path', 
        'status', 
        'moderation_status', 
        'moderation_notes'
    ];

    protected $casts = [
        'type' => MediaType::class,
        'moderation_status' => ModerationStatus::class
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
     * URL Thumbnail
     */
    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail_path) {
            return Storage::disk('s3')->url($this->thumbnail_path);
        }
        return null;
    }

    // [FIX] Tambahkan Relasi User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}