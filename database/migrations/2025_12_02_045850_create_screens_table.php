<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke hotel (Cascade delete)
            $table->foreignId('hotel_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code')->unique()->nullable(); // Device ID
            $table->string('location')->nullable();

            $table->unsignedInteger('resolution_width')->default(1920);
            $table->unsignedInteger('resolution_height')->default(1080);
            $table->string('orientation', 20)->default('landscape');

            // Logic Bisnis
            $table->decimal('price_per_play', 12, 2)->default(0); 
            
            // FITUR BARU: Kapasitas Inventory
            // Max slot iklan dalam sehari (misal: 1000 slot)
            $table->integer('max_plays_per_day')->default(1000); 
            
            // Batas durasi video per iklan (detik)
            $table->integer('max_duration_sec')->default(60);

            $table->boolean('is_active')->default(true);  // Status Admin
            $table->boolean('is_online')->default(false); // Status Device
            $table->json('allowed_categories')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['hotel_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};