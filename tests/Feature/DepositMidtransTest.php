<?php

use App\Models\User;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Services\MidtransService; 
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('processes midtrans webhook and updates wallet', function () {
    // 1. Setup User & Wallet
    $user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $user->id, 'balance' => 0]);

    // 2. Data Awal Deposit
    Deposit::create([
        'user_id' => $user->id,
        'order_id' => 'DEP-TEST-123',
        'amount' => 100000,
        'total_amount' => 100000,
        'status' => 'pending'
    ]);

    // 3. Payload Simulasi
    $payload = [
        'transaction_status' => 'settlement',
        'order_id' => 'DEP-TEST-123',
        'payment_type' => 'bank_transfer',
        'gross_amount' => 100000,
        'fraud_status' => 'accept'
    ];

    // 4. MOCK SERVICE
    // Kita paksa 'handleNotification' mengembalikan objek data dummy
    $this->mock(MidtransService::class, function (MockInterface $mock) use ($payload) {
        $mock->shouldReceive('handleNotification')
             ->once()
             ->andReturn((object) $payload);
    });

    // 5. Hit Endpoint (Tanpa Auth, karena Webhook itu public)
    $response = $this->postJson('/api/callback/midtrans', $payload);

    $response->assertOk();

    // 6. Assertions
    $this->assertDatabaseHas('deposits', ['order_id' => 'DEP-TEST-123', 'status' => 'paid']);
    $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 100000]);
});