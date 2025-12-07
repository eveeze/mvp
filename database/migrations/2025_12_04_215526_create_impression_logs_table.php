<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impression_logs', function (Blueprint $table) {
            $table->id(); // BigInt (Karena data ini akan sangat banyak)
            
            // Relasi ke Screen & Media
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            
            // Opsional: Relasi ke CampaignItem agar tracking lebih mudah
            // $table->foreignId('campaign_item_id')->nullable()->constrained();

            // Kapan tayang & berapa lama (Proof of Play)
            $table->timestamp('played_at');
            $table->integer('duration_sec'); // Durasi aktual tayang
            
            $table->timestamps();
            
            // Indexing sangat penting untuk performa Reporting/Dashboard
            $table->index(['screen_id', 'played_at']);
            $table->index(['media_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impression_logs');
    }
};