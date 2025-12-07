<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            
            // Harga dasar berdasarkan Bintang Hotel
            $table->unsignedTinyInteger('hotel_star_rating'); 
            
            // Durasi Paket (misal: 7 hari, 14 hari, 30 hari)
            $table->integer('duration_days');
            
            // Harga Paket (Rupiah)
            $table->decimal('base_price', 15, 2);
            
            $table->timestamps();
            
            // Mencegah duplikasi harga untuk kombinasi bintang & durasi yang sama
            $table->unique(['hotel_star_rating', 'duration_days'], 'rate_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_cards');
    }
};