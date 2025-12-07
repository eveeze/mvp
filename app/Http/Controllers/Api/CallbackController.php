<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\MidtransService;
use App\Services\WalletService;
use App\Mail\DepositReceived; // Import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; // Import

class CallbackController extends Controller
{
    public function handleMidtrans(Request $request, MidtransService $midtrans, WalletService $walletService)
    {
        $notification = $midtrans->handleNotification();

        if (!$notification) return response()->json(['message' => 'Invalid signature'], 403);

        $transactionStatus = $notification->transaction_status;
        $orderId = $notification->order_id;
        $fraud = $notification->fraud_status;

        $deposit = Deposit::where('order_id', $orderId)->first();

        if (!$deposit) return response()->json(['message' => 'Order not found'], 404);
        if ($deposit->status === 'paid') return response()->json(['message' => 'Already processed']);

        $newStatus = null;
        if ($transactionStatus == 'capture') {
            $newStatus = $fraud == 'challenge' ? 'pending' : 'paid';
        } elseif ($transactionStatus == 'settlement') {
            $newStatus = 'paid';
        } elseif ($transactionStatus == 'pending') {
            $newStatus = 'pending';
        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            $newStatus = 'failed';
        }

        if ($newStatus) {
            $deposit->status = $newStatus;
            $deposit->payment_details = json_encode($request->all());
            $deposit->save();

            if ($newStatus === 'paid') {
                try {
                    $walletService->creditBalance($deposit->user, $deposit->amount, "Topup Deposit #{$deposit->order_id}", $deposit);
                    
                    // [KIRIM EMAIL]
                    Mail::to($deposit->user->email)->queue(new DepositReceived($deposit));
                    
                } catch (\Exception $e) {
                    Log::error("Wallet Update Failed: " . $e->getMessage());
                }
            }
        }

        return response()->json(['message' => 'Callback processed']);
    }
}