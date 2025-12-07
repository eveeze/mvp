<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Tipe aset: video / image
            $table->string('type', 20)->default('video')->after('user_id');
            
            // Path Thumbnail (untuk preview di dashboard admin/advertiser)
            $table->string('thumbnail_path')->nullable()->after('path_optimized');
            
            // Status Moderasi (pending, approved, rejected)
            $table->string('moderation_status')->default('pending')->after('status');
            
            // Catatan jika ditolak (misal: "Gambar mengandung unsur sara")
            $table->text('moderation_notes')->nullable()->after('moderation_status');
            
            // Index untuk mempercepat query admin mencari yang 'pending'
            $table->index('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['type', 'thumbnail_path', 'moderation_status', 'moderation_notes']);
        });
    }
};