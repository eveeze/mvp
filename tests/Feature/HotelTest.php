<?php

use App\Models\User;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 1. Unauthenticated
it('rejects access to /api/hotels for unauthenticated user', function () {
    $this->getJson('/api/hotels')->assertStatus(401);
});

// 2. Unauthorized (Advertiser)
it('rejects access to /api/hotels for advertiser user', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    $this->actingAs($user)->getJson('/api/hotels')->assertStatus(403);
});

// 3. Create Hotel (Dengan Harga & Bintang)
it('allows superadmin to create a hotel with pricing details', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/hotels', [
            'name' => 'Luxury Hotel',
            'city' => 'Bali',
            'address' => 'Kuta',
            'contact_person' => 'Manager',
            'contact_phone' => '08123',
            // FITUR BARU HARI 1
            'star_rating' => 5, 
            'price_override' => 250000, 
            'is_active' => true
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.star_rating', 5)
        ->assertJsonPath('data.price_override', '250000.00');        
    $this->assertDatabaseHas('hotels', [
        'name' => 'Luxury Hotel',
        'star_rating' => 5,
        'price_override' => 250000
    ]);
});

// 4. List Hotels
it('allows superadmin to list hotels', function () {
    Hotel::query()->forceDelete(); // Bersihkan DB
    $admin = User::factory()->superAdmin()->create();
    Hotel::factory()->create();

    $this->actingAs($admin)
        ->getJson('/api/hotels')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');
});

// 5. Update Hotel
it('allows superadmin to update hotel details', function () {
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/hotels/{$hotel->id}", [
            'name' => 'Updated Name',
            'star_rating' => 4 // Update bintang
        ])
        ->assertOk()
        ->assertJsonPath('data.star_rating', 4);
});

// 6. Delete Hotel
it('allows superadmin to delete hotel', function () {
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/hotels/{$hotel->id}")
        ->assertOk();

    $this->assertSoftDeleted('hotels', ['id' => $hotel->id]);
});