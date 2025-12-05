<?php

use App\Models\User;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows superadmin to list pending media', function () {
    $admin = User::factory()->superAdmin()->create();
    
    // Buat 3 media: 2 pending, 1 approved
    Media::factory()->count(2)->create(['moderation_status' => 'pending']);
    Media::factory()->create(['moderation_status' => 'approved']);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/media?status=pending');

    $response->assertOk()
             ->assertJsonCount(2, 'data.data'); // Harus cuma 2 yang muncul
});

it('allows superadmin to approve media', function () {
    $admin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create(['moderation_status' => 'pending']);

    $this->actingAs($admin)
        ->putJson("/api/admin/media/{$media->id}/approve")
        ->assertOk();

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'moderation_status' => 'approved'
    ]);
});

it('allows superadmin to reject media with reason', function () {
    $admin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create(['moderation_status' => 'pending']);

    $this->actingAs($admin)
        ->putJson("/api/admin/media/{$media->id}/reject", [
            'reason' => 'Konten melanggar aturan'
        ])
        ->assertOk();

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'moderation_status' => 'rejected',
        'moderation_notes' => 'Konten melanggar aturan'
    ]);
});

it('forbids advertiser from accessing moderation endpoints', function () {
    $advertiser = User::factory()->create(['role' => 'advertiser']);
    $media = Media::factory()->create();

    $this->actingAs($advertiser)
        ->putJson("/api/admin/media/{$media->id}/approve")
        ->assertStatus(403);
});