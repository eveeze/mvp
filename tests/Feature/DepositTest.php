<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Deposit;

test('advertiser can create deposit request', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    
    $response = $this->actingAs($user)
        ->postJson('/api/deposits', [
            'amount' => 50000,
            'payment_method' => 'bank_transfer'
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('deposits', ['amount' => 50000, 'status' => 'pending']);
});

test('superadmin can approve deposit and balance increases', function () {
    // 1. Setup Data
    $advertiser = User::factory()->create(['role' => 'advertiser']);
    $wallet = Wallet::create(['user_id' => $advertiser->id, 'balance' => 0]);
    $deposit = Deposit::create([
        'wallet_id' => $wallet->id, 'amount' => 100000, 'status' => 'pending', 
        'payment_method' => 'manual'
    ]);

    // 2. Action Approve sebagai Admin
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->postJson("/api/admin/deposits/{$deposit->id}/approve")
         ->assertStatus(200);

    // 3. Cek Database (Saldo harus nambah)
    $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'balance' => 100000]);
    $this->assertDatabaseHas('deposits', ['id' => $deposit->id, 'status' => 'paid']);
});