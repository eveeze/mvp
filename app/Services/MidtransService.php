<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MidtransService
{
    protected string $serverKey;
    protected string $snapUrl;
    protected bool $isProduction;

    public function __construct()
    {
        $this->serverKey    = config('services.midtrans.server_key');
        $this->snapUrl      = config('services.midtrans.snap_url');
        $this->isProduction = config('services.midtrans.is_production');
    }

    /**
     * Create Snap Token
     */
    public function createSnapToken(string $orderId, int $amount, object $user)
    {
        // Auth Header: base64(ServerKey + ':')
        $auth = base64_encode($this->serverKey . ':');

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email'      => $user->email,
            ],
            'item_details' => [
                [
                    'id'       => 'DEPOSIT',
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => 'Deposit Saldo Ads',
                ]
            ],
            'enabled_payments' => ['gopay', 'bank_transfer', 'qris'], // Opsional: batasi metode
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $auth,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($this->snapUrl, $payload);

        if ($response->failed()) {
            throw new \Exception('Midtrans Error: ' . $response->body());
        }

        return $response->json('token');
    }

    /**
     * Validate Signature from Midtrans Callback
     * Signature = SHA512(order_id + status_code + gross_amount + ServerKey)
     */
    public function isValidSignature(array $notification): bool
    {
        $orderId    = $notification['order_id'];
        $statusCode = $notification['status_code'];
        $grossAmount = $notification['gross_amount'];
        $signature  = $notification['signature_key'];

        $calculated = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return $calculated === $signature;
    }
}