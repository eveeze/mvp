<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Atribut yang bisa diisi secara massal.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'city',
        'address',
        'contact_person',
        'contact_phone',
        'is_active', // Menambahkan field status
        'star_rating'
    ];

    /**
     * Casting tipe data native.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope untuk query hanya hotel yang aktif.
     * Cara pakai: Hotel::active()->get();
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relasi ke Screen.
     */
    public function screens()
    {
        return $this->hasMany(Screen::class);
    }
}