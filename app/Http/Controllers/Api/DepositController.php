<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepositController extends Controller
{
    protected $midtrans;

    public function __construct(MidtransService $midtrans)
    {
        $this->midtrans = $midtrans;
    }

    /**
     * User request deposit baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000', // Minimal deposit 10rb
        ]);

        $user = $request->user();
        $amount = $request->input('amount');
        
        // Generate Order ID Unik
        $orderId = 'DEP-' . time() . '-' . Str::upper(Str::random(5));

        // 1. Simpan ke database (Status: Pending)
        $deposit = Deposit::create([
            'user_id'      => $user->id,
            'order_id'     => $orderId,
            'amount'       => $amount,
            'total_amount' => $amount, // + Admin fee jika ada
            'status'       => 'pending',
        ]);

        // 2. Siapkan parameter Midtrans Snap
        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email'      => $user->email,
            ],
            // Opsional: Item Details agar muncul di email invoice Midtrans
            'item_details' => [
                [
                    'id'       => 'DEPOSIT',
                    'price'    => (int) $amount,
                    'quantity' => 1,
                    'name'     => 'Deposit Saldo Eveeze'
                ]
            ]
        ];

        try {
            // 3. Minta Snap Token ke Midtrans
            $snapToken = $this->midtrans->createSnapToken($params);

            // Update token di database
            $deposit->update(['snap_token' => $snapToken]);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'order_id'   => $orderId,
                    'snap_token' => $snapToken,
                    'amount'     => $amount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List histori deposit user.
     */
    public function index(Request $request)
    {
        $deposits = Deposit::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json(['data' => $deposits]);
    }
}