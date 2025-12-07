<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'city',
        'address',
        'contact_person',
        'contact_phone',
        'is_active',
        // Kolom Baru
        'star_rating',    // Sudah ada dari Fase Persiapan
        'price_override', // Baru ditambahkan di Hari 1
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'star_rating' => 'integer',
        'price_override' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function screens()
    {
        return $this->hasMany(Screen::class);
    }
}