<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            
            // Metrics
            $table->integer('cpu_usage')->nullable();    // Persen
            $table->integer('memory_usage')->nullable(); // MB
            $table->float('temperature')->nullable();    // Celcius
            $table->integer('uptime_sec')->nullable();   // Detik sejak boot
            $table->string('app_version')->nullable();   // Versi aplikasi player
            
            $table->timestamp('recorded_at')->useCurrent();
            
            // Data ini biasanya dihapus berkala (Retention Policy), tidak butuh timestamps created_at/updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_telemetries');
    }
};