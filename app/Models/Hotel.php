<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'address',
        'contact_person',
        'contact_phone',
    ];

    public function screens()
    {
        return $this->hasMany(Screen::class);
    }
}
