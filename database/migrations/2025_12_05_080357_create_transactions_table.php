<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke Wallet dan User
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Redundan tapi mempercepat query history user
            
            // Detail Uang
            $table->decimal('amount', 15, 2); // Jumlah uang
            $table->string('type', 10); // 'credit' (masuk) atau 'debit' (keluar)
            $table->string('description'); // Keterangan, misal: "Bayar Campaign #123"
            
            // Polymorphic Relation (Transaksi ini terkait apa? Deposit? Campaign? Refund?)
            // Akan membuat kolom: reference_id dan reference_type
            $table->nullableMorphs('reference'); 
            
            $table->decimal('balance_after', 15, 2); // Snapshot saldo setelah transaksi (Opsional tapi bagus untuk audit)
            
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};