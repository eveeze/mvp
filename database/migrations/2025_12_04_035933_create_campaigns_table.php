<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Header Campaign
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            
            // Total biaya yang dibayar user (snapshot)
            $table->decimal('total_cost', 15, 2);
            
            // Status: active, finished, cancelled
            $table->string('status')->default('active');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index untuk mempercepat query overlap jadwal
            $table->index(['start_date', 'end_date', 'status']);
        });

        // Tabel Detail Item (Screen yang dipesan)
        Schema::create('campaign_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained(); 
            $table->foreignId('media_id')->constrained();
            
            // Jumlah tayang per hari di layar ini
            $table->integer('plays_per_day'); 
            
            // Harga per tayang saat transaksi terjadi (Snapshot harga)
            $table->decimal('price_per_play', 12, 2); 
            
            $table->timestamps();
            
            $table->index('screen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_items');
        Schema::dropIfExists('campaigns');
    }
};