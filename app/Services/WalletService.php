<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Menambah saldo user (Credit) dengan aman.
     */
    public function creditBalance(User $user, float $amount, string $description = 'Topup'): Wallet
    {
        return DB::transaction(function () use ($user, $amount) {
            // Lock row wallet agar tidak ada proses lain yang mengubahnya bersamaan
            $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
            
            // Re-fetch dengan lock
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            $wallet->balance += $amount;
            $wallet->save();

            // Opsional: Catat riwayat mutasi (Transaction History) di sini
            // TransactionHistory::create([...]);

            return $wallet;
        });
    }

    /**
     * Mengurangi saldo user (Debit)
     */
    public function debitBalance(User $user, float $amount): bool
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet || $wallet->balance < $amount) {
                return false; // Saldo kurang
            }

            $wallet->balance -= $amount;
            $wallet->save();

            return true;
        });
    }
}