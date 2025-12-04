<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('file_name');      // Nama asli file (contoh: iklan.mp4)
            $table->string('mime_type');      // video/mp4
            $table->bigInteger('size');       // ukuran bytes
            $table->integer('duration')->nullable(); // Durasi (detik)
            
            // Path
            $table->string('path_original')->nullable();  // File mentah upload user
            $table->string('path_optimized')->nullable(); // Path ke file playlist .m3u8 di S3
            
            // Status: pending, processing, completed, failed
            $table->string('status')->default('pending'); 
            
            $table->timestamps();
            $table->softDeletes(); // Agar data aman (tidak langsung hilang saat dihapus)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};