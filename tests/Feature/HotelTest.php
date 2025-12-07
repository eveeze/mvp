<?php

use App\Models\User;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects access to /api/hotels for unauthenticated user', function () {
    $this->getJson('/api/hotels')->assertStatus(401);
});

it('rejects access to /api/hotels for advertiser user', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    $this->actingAs($user)->getJson('/api/hotels')->assertStatus(403);
});

it('allows superadmin to create a hotel with pricing details', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/hotels', [
            'name' => 'Luxury Hotel',
            'city' => 'Bali',
            'address' => 'Kuta',
            'contact_person' => 'Manager',
            'contact_phone' => '08123',
            'star_rating' => 5, 
            'price_override' => 250000, 
            'is_active' => true
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.star_rating', 5)
        // [FIX] Gunakan string desimal
        ->assertJsonPath('data.price_override', 250000);
});

it('allows superadmin to list hotels', function () {
    Hotel::query()->forceDelete();
    $admin = User::factory()->superAdmin()->create();
    Hotel::factory()->create();

    $this->actingAs($admin)
        ->getJson('/api/hotels')
        ->assertOk()
        // [FIX] Resource Collection ada di 'data'
        ->assertJsonCount(1, 'data');
});

it('allows superadmin to update hotel details', function () {
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/hotels/{$hotel->id}", [
            'name' => 'Updated Name',
            'star_rating' => 4
        ])
        ->assertOk()
        ->assertJsonPath('data.star_rating', 4);
});

it('allows superadmin to delete hotel', function () {
    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/hotels/{$hotel->id}")
        ->assertOk();

    $this->assertSoftDeleted('hotels', ['id' => $hotel->id]);
});