<?php

use App\Models\User;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Services\MidtransService; // Import Service
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $this->user->id, 'balance' => 0]);
});

it('processes midtrans webhook and updates wallet', function () {
    // 1. Data Awal
    Deposit::create([
        'user_id' => $this->user->id,
        'order_id' => 'DEP-TEST-123',
        'amount' => 100000,
        'total_amount' => 100000,
        'status' => 'pending'
    ]);

    // 2. Payload Simulasi
    $payload = [
        'transaction_status' => 'settlement',
        'order_id' => 'DEP-TEST-123',
        'payment_type' => 'bank_transfer',
        'gross_amount' => 100000,
        'fraud_status' => 'accept'
    ];

    // 3. MOCK SERVICE
    // Kita paksa 'handleNotification' mengembalikan objek data dummy
    // Tanpa perlu validasi signature asli yang ribet di test
    $this->mock(MidtransService::class, function (MockInterface $mock) use ($payload) {
        $mock->shouldReceive('handleNotification')
             ->once()
             ->andReturn((object) $payload);
    });

    // 4. Hit Endpoint (Tanpa Auth)
    $response = $this->postJson('/api/callback/midtrans', $payload);

    $response->assertOk();

    // 5. Assertions
    $this->assertDatabaseHas('deposits', ['order_id' => 'DEP-TEST-123', 'status' => 'paid']);
    $this->assertDatabaseHas('wallets', ['user_id' => $this->user->id, 'balance' => 100000]);
});