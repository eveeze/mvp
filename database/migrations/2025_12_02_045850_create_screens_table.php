<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();

            // Relasi ke hotels
            $table->foreignId('hotel_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');                     // Nama layar, mis: "Lobby Screen 1"
            $table->string('code', 100)->nullable();    // Kode internal, optional
            $table->string('location')->nullable();     // Lokasi fisik, mis "Lobby", "Lift Area"

            // Resolusi
            $table->unsignedInteger('resolution_width')->nullable();   // px
            $table->unsignedInteger('resolution_height')->nullable();  // px

            // Orientasi: 'landscape' | 'portrait'
            $table->string('orientation', 20)->default('landscape');

            // Status online / offline
            $table->boolean('is_online')->default(true);

            // Kategori iklan yang diizinkan (disimpan sebagai JSON array)
            $table->json('allowed_categories')->nullable();

            $table->timestamps();

            // Index tambahan
            $table->index(['hotel_id', 'is_online']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
