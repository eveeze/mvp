<?php

use App\Models\User;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('rejects access to /api/hotels for unauthenticated user', function () {
    $response = $this->getJson('/api/hotels');

    $response->assertStatus(401);
});

it('rejects access to /api/hotels for advertiser user', function () {
    $advertiser = User::factory()->create([
        'role' => 'advertiser',
    ]);

    $token = $advertiser->createToken('api')->plainTextToken;

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/hotels');

    $response->assertStatus(403);
});

it('allows superadmin to create a hotel', function () {
    $superadmin = User::factory()->create([
        'role'     => 'superadmin',
        'password' => Hash::make('password123'),
    ]);

    $token = $superadmin->createToken('api')->plainTextToken;

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/hotels', [
            'name'           => 'Hotel Network A',
            'city'           => 'Jakarta',
            'address'        => 'Jl. Contoh No. 1',
            'contact_person' => 'Admin Hotel',
            'contact_phone'  => '08123456789',
        ]);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'city', 'address', 'contact_person', 'contact_phone'],
        ]);

    $this->assertDatabaseHas('hotels', [
        'name' => 'Hotel Network A',
    ]);
});

it('allows superadmin to list, update and delete hotels', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $token = $superadmin->createToken('api')->plainTextToken;

    $hotel = Hotel::factory()->create([
        'name' => 'Old Hotel Name',
    ]);

    // List
    $listResponse = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/hotels');

    $listResponse
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Update
    $updateResponse = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/hotels/'.$hotel->id, [
            'name' => 'New Hotel Name',
        ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.name', 'New Hotel Name');

    $this->assertDatabaseHas('hotels', [
        'id'   => $hotel->id,
        'name' => 'New Hotel Name',
    ]);

    // Delete
    $deleteResponse = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/hotels/'.$hotel->id);

    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('hotels', [
        'id' => $hotel->id,
    ]);
});
