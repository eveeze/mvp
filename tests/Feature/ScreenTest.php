<?php

use App\Models\User;
use App\Models\Hotel;
use App\Models\Screen;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 1. Create Screen (Null Price)
it('allows superadmin to create a screen with null price', function () {
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    $payload = [
        'name' => 'Lobby Screen',
        'code' => 'SCR-NULL-PRICE',
        'resolution_width' => 1920,
        'resolution_height' => 1080,
        'orientation' => 'landscape',
        'max_plays_per_day' => 100,
        'max_duration_sec' => 60,
        // FITUR HARI 1: Nullable Price
        'price_per_play' => null, 
        'is_active' => true,
        'is_online' => false
    ];

    $response = $this->actingAs($admin)
        ->postJson("/api/hotels/{$hotel->id}/screens", $payload);

    $response->assertCreated();
    
    $this->assertDatabaseHas('screens', [
        'code' => 'SCR-NULL-PRICE',
        'price_per_play' => null 
    ]);
});

// 2. List Screens
it('allows superadmin to list screens', function () {
    Screen::query()->forceDelete(); // Bersihkan DB
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();
    Screen::factory()->count(2)->create(['hotel_id' => $hotel->id]);

    $this->actingAs($admin)
        ->getJson("/api/hotels/{$hotel->id}/screens")
        ->assertOk()
        ->assertJsonCount(2, 'data.data');
});

// 3. Update Screen
it('allows superadmin to update screen', function () {
    $admin = User::factory()->superAdmin()->create();
    $screen = Screen::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/hotels/{$screen->hotel_id}/screens/{$screen->id}", [
            'name' => 'Updated Screen',
            'resolution_width' => 1920,
            'resolution_height' => 1080,
            'orientation' => 'landscape',
            'price_per_play' => 50000,
            // [FIX] Tambahkan field wajib ini
            'max_plays_per_day' => 100,
            'max_duration_sec' => 60,
            'is_online' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.price_per_play', '50000.00'); // String check
});