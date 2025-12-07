<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            // Menambahkan unique constraint untuk mencegah duplikasi nama
            $table->string('name')->unique(); 
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            
            // Menambahkan status untuk kontrol operasional (Production best practice)
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Soft deletes agar data aman dan bisa direstore jika tidak sengaja terhapus
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};