<?php

use App\Models\Hotel;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

//
// 1) GUEST TIDAK BOLEH AKSES SCREEN HOTEL
//
it('rejects access to hotel screens for unauthenticated user', function () {
    $hotel = Hotel::factory()->create();

    $response = $this->getJson("/api/hotels/{$hotel->id}/screens");

    $response->assertStatus(401);
});

//
// 2) ADVERTISER TIDAK BOLEH AKSES SCREEN HOTEL (403)
//
it('rejects access to hotel screens for advertiser', function () {
    $hotel = Hotel::factory()->create();

    $advertiser = User::factory()->create([
        'role'     => 'advertiser',
        'password' => Hash::make('password123'),
    ]);

    $token = $advertiser->createToken('api')->plainTextToken;

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/hotels/{$hotel->id}/screens");

    $response->assertStatus(403);
});

//
// 3) SUPERADMIN BISA CREATE SCREEN UNTUK HOTEL
//
it('allows superadmin to create a screen for a hotel', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'Hotel Network A',
    ]);

    $superadmin = User::factory()->create([
        'role'     => 'superadmin',
        'password' => Hash::make('password123'),
    ]);

    $token = $superadmin->createToken('api')->plainTextToken;

    $payload = [
        'name'              => 'Lobby Screen 1',
        'code'              => 'SCR-LOBBY-1',
        'location'          => 'Lobby',
        'resolution_width'  => 1920,
        'resolution_height' => 1080,
        'orientation'       => 'landscape',
        'is_online'         => true,
        'allowed_categories'=> ['food', 'travel'],
    ];

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/hotels/{$hotel->id}/screens", $payload);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'hotel_id',
                'name',
                'code',
                'location',
                'resolution_width',
                'resolution_height',
                'orientation',
                'is_online',
                'allowed_categories',
            ],
        ])
        ->assertJsonPath('data.hotel_id', $hotel->id)
        ->assertJsonPath('data.name', 'Lobby Screen 1');

    $this->assertDatabaseHas('screens', [
        'hotel_id' => $hotel->id,
        'name'     => 'Lobby Screen 1',
        'code'     => 'SCR-LOBBY-1',
    ]);
});

//
// 4) SUPERADMIN BISA LIST HANYA SCREEN MILIK HOTEL TERTENTU
//
it('allows superadmin to list screens for a specific hotel', function () {
    $hotelA = Hotel::factory()->create(['name' => 'Hotel A']);
    $hotelB = Hotel::factory()->create(['name' => 'Hotel B']);

    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $token = $superadmin->createToken('api')->plainTextToken;

    // 2 screen milik hotel A
    Screen::factory()->count(2)->create([
        'hotel_id' => $hotelA->id,
    ]);

    // 1 screen milik hotel B
    Screen::factory()->create([
        'hotel_id' => $hotelB->id,
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/hotels/{$hotelA->id}/screens");

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.hotel_id', $hotelA->id);
});

//
// 5) SUPERADMIN BISA UPDATE & DELETE SCREEN
//
it('allows superadmin to update and delete a screen', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $token = $superadmin->createToken('api')->plainTextToken;

    $hotel = Hotel::factory()->create();
    $screen = Screen::factory()->create([
        'hotel_id' => $hotel->id,
        'name'     => 'Old Name',
    ]);

    // Update
    $updateResponse = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->putJson("/api/hotels/{$hotel->id}/screens/{$screen->id}", [
            'name'      => 'Updated Screen Name',
            'is_online' => false,
        ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Screen Name')
        ->assertJsonPath('data.is_online', false);

    $this->assertDatabaseHas('screens', [
        'id'        => $screen->id,
        'name'      => 'Updated Screen Name',
        'is_online' => false,
    ]);

    // Delete
    $deleteResponse = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson("/api/hotels/{$hotel->id}/screens/{$screen->id}");

    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('screens', [
        'id' => $screen->id,
    ]);
});
