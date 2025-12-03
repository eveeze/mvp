<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepositController extends Controller
{
    protected MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:10000'],
        ]);

        $user = $request->user();
        
        // Pastikan wallet ada
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        // Generate Order ID Unik: DEP-[TIMESTAMP]-[RANDOM]
        $orderId = 'DEP-' . time() . '-' . Str::random(5);

        try {
            // 1. Get Snap Token from Midtrans
            $snapToken = $this->midtransService->createSnapToken($orderId, $request->amount, $user);

            // 2. Simpan Deposit ke DB (Status Pending)
            $deposit = Deposit::create([
                'wallet_id'  => $wallet->id,
                'amount'     => $request->amount,
                'status'     => 'pending',
                'order_id'   => $orderId,
                'snap_token' => $snapToken,
            ]);

            return response()->json([
                'message' => 'Deposit created',
                'data'    => [
                    'deposit_id' => $deposit->id,
                    'snap_token' => $snapToken, // Frontend pakai ini utk window.snap.pay(token)
                    'order_id'   => $orderId,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}