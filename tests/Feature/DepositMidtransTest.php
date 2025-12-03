<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Deposit;
use Illuminate\Support\Facades\Http;

test('advertiser can create deposit and get snap token', function () {
    // Mock Midtrans Response
    Http::fake([
        '*.midtrans.com/*' => Http::response(['token' => 'SNAP-TOKEN-123'], 200)
    ]);

    $user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $user->id, 'balance' => 0]);

    $response = $this->actingAs($user)
        ->postJson('/api/deposits', [
            'amount' => 50000,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.snap_token', 'SNAP-TOKEN-123');

    $this->assertDatabaseHas('deposits', [
        'amount'     => 50000,
        'snap_token' => 'SNAP-TOKEN-123',
        'status'     => 'pending'
    ]);
});

test('midtrans callback settlement updates deposit to paid', function () {
    // Setup Data
    $user = User::factory()->create(['role' => 'advertiser']);
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    $orderId = 'DEP-TEST-MIDTRANS';
    
    $deposit = Deposit::create([
        'wallet_id' => $wallet->id,
        'amount'    => 100000,
        'status'    => 'pending',
        'order_id'  => $orderId,
    ]);

    // Mock Config Server Key
    $serverKey = 'SB-Mid-server-TEST';
    config(['services.midtrans.server_key' => $serverKey]);

    // Payload Callback Midtrans (Settlement)
    $payload = [
        'transaction_status' => 'settlement',
        'order_id'           => $orderId,
        'status_code'        => '200',
        'gross_amount'       => '100000.00', // String format
        'payment_type'       => 'bank_transfer',
        'signature_key'      => hash('sha512', $orderId . '200' . '100000.00' . $serverKey)
    ];

    // Hit Callback Endpoint
    $response = $this->postJson('/api/callback/midtrans', $payload);

    $response->assertOk();

    // Check DB
    $this->assertDatabaseHas('deposits', ['id' => $deposit->id, 'status' => 'paid']);
    $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'balance' => 100000]);
});