<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Exception;

class WalletService
{
    /**
     * Advertiser membuat request deposit
     */
    public function createDeposit(User $user, float $amount, string $paymentMethod): Deposit
    {
        // 1. Pastikan user punya wallet (create jika belum ada)
        $wallet = $user->wallet ?? Wallet::create([
            'user_id' => $user->id, 
            'balance' => 0
        ]);

        // 2. Buat record deposit status PENDING
        return Deposit::create([
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            // Generate kode unik sederhana
            'payment_reference' => 'DEP-' . time() . rand(100, 999),
        ]);
    }

    /**
     * Superadmin menyetujui deposit -> Saldo Bertambah
     */
    public function approveDeposit(Deposit $deposit): void
    {
        if ($deposit->status === 'paid') {
            throw new Exception("Deposit ini sudah disetujui sebelumnya.");
        }

        // Mulai transaksi database (PENTING untuk data uang)
        DB::transaction(function () use ($deposit) {
            // 1. Update status deposit jadi 'paid'
            $deposit->update(['status' => 'paid']);

            // 2. Tambahkan saldo ke wallet user
            $deposit->wallet->increment('balance', $deposit->amount);
        });
    }
}