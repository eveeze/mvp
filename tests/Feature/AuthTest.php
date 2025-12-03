<?php

/** @var \Tests\TestCase $this */

use App\Models\User;
use Illuminate\Support\Facades\Hash;

//
// 1) REGISTER ADVERTISER
//
it('can register advertiser and returns token', function () {
    $response = $this->postJson('/api/auth/register-advertiser', [
        'name'     => 'Tito Advertiser',
        'email'    => 'tito@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'user'  => ['id', 'name', 'email', 'role'],
            'token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'tito@example.com',
        'role'  => 'advertiser',
    ]);
});

//
// 2) LOGIN ADVERTISER VIA /api/auth/login
//
it('can login advertiser with valid credentials', function () {
    $user = User::factory()->create([
        'email'    => 'login@example.com',
        'password' => Hash::make('password123'),
        'role'     => 'advertiser',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => 'login@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'user'  => ['id', 'name', 'email', 'role'],
            'token',
        ])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'advertiser');
});

//
// 3) LOGIN SUPERADMIN VIA /api/auth/login
//
it('can login superadmin with valid credentials', function () {
    $user = User::factory()->create([
        'email'    => 'super@example.com',
        'password' => Hash::make('password123'),
        'role'     => 'superadmin',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => 'super@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'superadmin');
});

//
// 4) LOGIN GAGAL (PASSWORD SALAH)
//
it('rejects login with wrong password', function () {
    User::factory()->create([
        'email'    => 'wrongpass@example.com',
        'password' => Hash::make('password123'),
        'role'     => 'advertiser',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => 'wrongpass@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

//
// 5) /api/me MENGEMBALIKAN USER KALAU ADA TOKEN
//
it('returns current user on /api/me when authenticated', function () {
    $user = User::factory()->create([
        'role' => 'advertiser',
    ]);

    $token = $user->createToken('api')->plainTextToken;

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/me');

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

//
// 6) /api/me DITOLAK KALAU TIDAK ADA TOKEN
//
it('rejects /api/me without token', function () {
    $response = $this->getJson('/api/me');

    $response->assertStatus(401);
});
