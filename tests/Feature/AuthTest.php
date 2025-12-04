<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// --- REGISTER ---
it('can register advertiser', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'New Advertiser',
        'email' => 'new@ads.com',
        'password' => 'password',
        'password_confirmation' => 'password'
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user', 'token']);

    $this->assertDatabaseHas('users', ['email' => 'new@ads.com']);
});

// --- LOGIN ---
it('can login with valid credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

it('rejects invalid login', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertStatus(422); // Validation error or 401 depending on implement
});

// --- PROFILE & DASHBOARD ---
it('can get dashboard stats', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    
    $this->actingAs($user)
        ->getJson('/api/dashboard/stats')
        ->assertOk()
        ->assertJsonStructure(['data' => ['wallet_balance', 'active_campaigns']]);
});

it('can update profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', ['name' => 'Updated Name'])
        ->assertOk();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
});

it('can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/logout')
        ->assertOk();
});