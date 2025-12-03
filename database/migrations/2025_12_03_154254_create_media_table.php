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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Uploader
            $table->string('file_name');      // Nama file asli (iklan_sepatu.mp4)
            $table->string('mime_type');      // video/mp4
            $table->bigInteger('size');       // bytes
            
            // Path Processing
            $table->string('path_original');  // Path file mentah di storage
            $table->string('path_hls')->nullable(); // Path .m3u8 final di MinIO
            
            // Status Konversi
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};