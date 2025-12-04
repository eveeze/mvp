<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\MidtransService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    public function handleMidtrans(Request $request, MidtransService $midtrans, WalletService $walletService)
    {
        // 1. Terima & Validasi Notifikasi Midtrans
        $notification = $midtrans->handleNotification();

        if (!$notification) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = $notification->transaction_status;
        $type = $notification->payment_type;
        $orderId = $notification->order_id;
        $fraud = $notification->fraud_status;

        // Cari transaksi deposit kita berdasarkan Order ID
        $deposit = Deposit::where('order_id', $orderId)->first();

        if (!$deposit) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Idempotency Check: Jika sudah 'paid', jangan proses lagi (untuk menghindari saldo ganda)
        if ($deposit->status === 'paid') {
            return response()->json(['message' => 'Already processed']);
        }

        // 2. Tentukan Status Akhir
        $newStatus = null;

        if ($transactionStatus == 'capture') {
            // Untuk pembayaran kartu kredit
            if ($fraud == 'challenge') {
                $newStatus = 'pending'; // Perlu review manual di dashboard Midtrans
            } else {
                $newStatus = 'paid';
            }
        } elseif ($transactionStatus == 'settlement') {
            // Untuk transfer bank, gopay, dll (Uang sudah masuk)
            $newStatus = 'paid';
        } elseif ($transactionStatus == 'pending') {
            $newStatus = 'pending';
        } elseif ($transactionStatus == 'deny') {
            $newStatus = 'failed';
        } elseif ($transactionStatus == 'expire') {
            $newStatus = 'expired';
        } elseif ($transactionStatus == 'cancel') {
            $newStatus = 'failed';
        }

        // 3. Update Database & Saldo
        if ($newStatus) {
            $deposit->status = $newStatus;
            $deposit->payment_details = $request->all(); // Simpan log raw dari midtrans
            $deposit->save();

            // Jika status menjadi PAID, tambahkan saldo ke Wallet User
            if ($newStatus === 'paid') {
                try {
                    $walletService->creditBalance($deposit->user, $deposit->amount);
                    Log::info("Saldo ditambahkan ke User ID: {$deposit->user_id} sebesar {$deposit->amount}");
                } catch (\Exception $e) {
                    Log::error("Gagal menambah saldo wallet: " . $e->getMessage());
                    // Catatan: Jika gagal update wallet tapi status deposit paid, 
                    // ini perlu penanganan manual/log khusus.
                }
            }
        }

        return response()->json(['message' => 'Callback processed']);
    }
}