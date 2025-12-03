<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    public function handleMidtrans(Request $request, MidtransService $midtransService)
    {
        $payload = $request->all();

        // 1. Validasi Signature
        if (!$midtransService->isValidSignature($payload)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // 2. Ambil Data Penting
        $orderId = $payload['order_id'];
        $transactionStatus = $payload['transaction_status'];
        $fraudStatus = $payload['fraud_status'] ?? null;
        $paymentType = $payload['payment_type'] ?? null;

        // 3. Cari Deposit
        $deposit = Deposit::where('order_id', $orderId)->first();
        if (!$deposit) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($deposit->status === 'paid') {
            return response()->json(['message' => 'Already paid'], 200);
        }

        // 4. Tentukan Status Baru
        $newStatus = null;
        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $newStatus = 'pending'; // Perlu review manual di dashboard midtrans
            } else {
                $newStatus = 'paid';
            }
        } else if ($transactionStatus == 'settlement') {
            $newStatus = 'paid';
        } else if ($transactionStatus == 'pending') {
            $newStatus = 'pending';
        } else if (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            $newStatus = 'failed';
        }

        // 5. Update DB (Atomic Transaction)
        if ($newStatus) {
            DB::transaction(function () use ($deposit, $newStatus, $payload, $paymentType) {
                // Update Deposit
                $deposit->update([
                    'status'       => $newStatus,
                    'payment_type' => $paymentType,
                    'raw_response' => $payload
                ]);

                // Jika PAID, tambah saldo wallet
                if ($newStatus === 'paid') {
                    $wallet = Wallet::lockForUpdate()->find($deposit->wallet_id);
                    $wallet->increment('balance', $deposit->amount);
                }
            });
        }

        return response()->json(['success' => true]);
    }
}