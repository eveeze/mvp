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
    
    $this->actingAs($user)
        ->getJson('/api/hotels')
        ->assertStatus(403);
});

it('allows superadmin to create a hotel', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/hotels', [
            'name' => 'Hotel Indonesia',
            'city' => 'Jakarta',
            'address' => 'Bundaran HI',
            'contact_person' => 'Manager',
            'contact_phone'  => '08123456789',
        ]);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'city', 'address'],
        ]);
});

it('allows superadmin to list, update and delete hotels', function () {
    // 1. Bersihkan Data Lama (PENTING: forceDelete untuk bypass soft deletes)
    Hotel::query()->forceDelete();

    $admin = User::factory()->superAdmin()->create();
    $hotel = Hotel::factory()->create();

    // List
    // Karena pakai paginate, data hotel ada di 'data.data'
    $this->actingAs($admin)
        ->getJson('/api/hotels')
        ->assertOk()
        ->assertJsonCount(1, 'data.data'); 

    // Update
    $this->actingAs($admin)
        ->putJson("/api/hotels/{$hotel->id}", ['name' => 'Updated Hotel'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Hotel');

    // Delete
    $this->actingAs($admin)
        ->deleteJson("/api/hotels/{$hotel->id}")
        ->assertOk();
        
    $this->assertSoftDeleted('hotels', ['id' => $hotel->id]);
});