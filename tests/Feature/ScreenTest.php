<?php

use App\Models\User;
use App\Models\Hotel;
use App\Models\Screen;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects access to hotel screens for unauthenticated user', function () {
    $hotel = Hotel::factory()->create();
    $this->getJson("/api/hotels/{$hotel->id}/screens")->assertStatus(401);
});

it('rejects access to hotel screens for advertiser', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    $hotel = Hotel::factory()->create();
    
    $this->actingAs($user)
        ->getJson("/api/hotels/{$hotel->id}/screens")
        ->assertStatus(403);
});

it('allows superadmin to create a screen for a hotel', function () {
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    $payload = [
        'name' => 'Lobby Screen',
        'code' => 'SCR-TEST-001',
        'price_per_play' => 15000,
        'resolution_width' => 1920,
        'resolution_height' => 1080,
        'orientation' => 'landscape',
        'max_plays_per_day' => 100,
        'is_active' => true,
        'is_online' => false
    ];

    $response = $this->actingAs($admin)
        ->postJson("/api/hotels/{$hotel->id}/screens", $payload);

    $response
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'hotel_id']]);
});

it('allows superadmin to list screens for a specific hotel', function () {
    // 1. Bersihkan DB agar hitungan akurat
    Screen::query()->forceDelete();

    $admin = User::factory()->superAdmin()->create();
    $hotelA = Hotel::factory()->create();
    $hotelB = Hotel::factory()->create();

    Screen::factory()->count(2)->create(['hotel_id' => $hotelA->id]);
    Screen::factory()->count(3)->create(['hotel_id' => $hotelB->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/hotels/{$hotelA->id}/screens");

    $response
        ->assertOk()
        // [FIX] Cek 'data.data' karena response menggunakan Pagination
        ->assertJsonCount(2, 'data.data') 
        // [FIX] Path JSON juga harus disesuaikan
        ->assertJsonPath('data.data.0.hotel_id', $hotelA->id);
});

it('allows superadmin to update and delete a screen', function () {
    $admin = User::factory()->superAdmin()->create();
    $screen = Screen::factory()->create();

    // Update
    $this->actingAs($admin)
        ->putJson("/api/hotels/{$screen->hotel_id}/screens/{$screen->id}", [
            'name' => 'Updated Screen Name',
            'is_online' => false,
            'resolution_width' => 1920,
            'resolution_height' => 1080,
            'orientation' => 'landscape',
            'price_per_play' => 10000,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Screen Name');

    // Delete
    $this->actingAs($admin)
        ->deleteJson("/api/hotels/{$screen->hotel_id}/screens/{$screen->id}")
        ->assertOk();

    $this->assertSoftDeleted('screens', ['id' => $screen->id]);
});