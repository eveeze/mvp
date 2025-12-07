<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Order ID unik dari sistem kita (misal: DEP-12345678)
            $table->string('order_id')->unique();
            
            $table->decimal('amount', 15, 2);
            $table->decimal('admin_fee', 10, 2)->default(0); // Jika ada biaya admin
            $table->decimal('total_amount', 15, 2); // amount + fee
            
            // Status: pending, paid, failed, expired
            $table->string('status')->default('pending');
            
            // Token dari Midtrans untuk frontend menampilkan popup pembayaran
            $table->string('snap_token')->nullable();
            
            // Data tambahan dari callback (bank pengirim, waktu bayar, dll)
            $table->json('payment_details')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};