<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function creditBalance(User $user, float $amount): Wallet
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            $wallet->balance += $amount;
            $wallet->save();
            return $wallet;
        });
    }

    public function debitBalance(User $user, float $amount): bool
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            // Jika wallet tidak ada atau saldo kurang, return FALSE
            if (!$wallet || $wallet->balance < $amount) {
                return false; 
            }

            $wallet->balance -= $amount;
            $wallet->save();

            return true;
        });
    }
}