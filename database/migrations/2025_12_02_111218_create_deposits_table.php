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
            
            // Relasi ke Wallet
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            
            // Data Dasar Deposit
            $table->integer('amount');
            $table->string('status')->default('pending'); // pending, paid, failed
            
            // Kolom Pembayaran (Midtrans / Manual)
            // PENTING: nullable() agar tidak error saat create awal
            $table->string('payment_method')->nullable(); 
            
            // Kolom Khusus Midtrans
            $table->string('order_id')->nullable()->unique(); // ID unik kita (DEP-xxx)
            $table->string('snap_token')->nullable();       // Token dari Midtrans Snap
            $table->string('payment_type')->nullable();     // Tipe bayar (gopay, bank_transfer, dll)
            $table->json('raw_response')->nullable();       // Log response lengkap
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};