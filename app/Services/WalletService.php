<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Enums\TransactionType;
class WalletService
{
    /**
     * Menambah saldo (Topup / Refund).
     * Mencatat ke tabel transactions sebagai 'credit'.
     */
    public function creditBalance(User $user, float $amount, string $description, ?Model $reference = null): Wallet
    {
        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
            
            // Lock row untuk mencegah race condition
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            
            $wallet->balance += $amount;
            $wallet->save();

            // Catat Transaksi
            Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => TransactionType::CREDIT,
                'description' => $description,
                'reference_id' => $reference?->id,
                'reference_type' => $reference ? get_class($reference) : null,
                'balance_after' => $wallet->balance
            ]);

            return $wallet;
        });
    }

    /**
     * Mengurangi saldo (Pembayaran Campaign).
     * Mencatat ke tabel transactions sebagai 'debit'.
     * Return TRUE jika sukses, FALSE jika saldo kurang.
     */
    public function debitBalance(User $user, float $amount, string $description, ?Model $reference = null): bool
    {
        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            // Cek kecukupan saldo
            if (!$wallet || $wallet->balance < $amount) {
                return false; 
            }

            $wallet->balance -= $amount;
            $wallet->save();

            // Catat Transaksi
            Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => TransactionType::DEBIT,
                'description' => $description,
                'reference_id' => $reference?->id,
                'reference_type' => $reference ? get_class($reference) : null,
                'balance_after' => $wallet->balance
            ]);

            return true;
        });
    }
}