<?php

use App\Models\Screen;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns playlist ONLY for ACTIVE campaigns and APPROVED media', function () {
    $screen = Screen::factory()->create(['code' => 'SCR-PLAY-TEST', 'is_active' => true]);
    $user = User::factory()->create();
    
    // Media Approved
    $mediaApproved = Media::factory()->create(['status' => 'completed', 'moderation_status' => 'approved', 'user_id' => $user->id]);
    // Media Pending
    $mediaPending = Media::factory()->create(['status' => 'completed', 'moderation_status' => 'pending', 'user_id' => $user->id]);

    // Campaign 1: Active & Approved Media -> HARUS MUNCUL
    $c1 = Campaign::factory()->create(['user_id' => $user->id, 'start_date' => now(), 'end_date' => now(), 'status' => 'active']);
    $c1->items()->create(['screen_id' => $screen->id, 'media_id' => $mediaApproved->id, 'plays_per_day' => 10, 'price_per_play' => 100]);

    // Campaign 2: Active TAPI Media Pending -> TIDAK BOLEH MUNCUL
    $c2 = Campaign::factory()->create(['user_id' => $user->id, 'start_date' => now(), 'end_date' => now(), 'status' => 'active']);
    $c2->items()->create(['screen_id' => $screen->id, 'media_id' => $mediaPending->id, 'plays_per_day' => 10, 'price_per_play' => 100]);

    // Campaign 3: Pending Review -> TIDAK BOLEH MUNCUL
    $c3 = Campaign::factory()->create(['user_id' => $user->id, 'start_date' => now(), 'end_date' => now(), 'status' => 'pending_review']);
    $c3->items()->create(['screen_id' => $screen->id, 'media_id' => $mediaApproved->id, 'plays_per_day' => 10, 'price_per_play' => 100]);

    $response = $this->getJson('/api/player/playlist?device_id=SCR-PLAY-TEST');

    $response->assertOk();
    
    // Hanya c1 yang boleh lolos
    $playlist = $response->json('playlist');
    expect($playlist)->toHaveCount(1);
    expect($playlist[0]['campaign_id'])->toBe($c1->id);
});

it('returns empty playlist if no active campaign', function () {
    $screen = Screen::factory()->create(['code' => 'SCR-EMPTY']);

    // Campaign masa depan (Besok)
    Campaign::factory()->create([
        'start_date' => now()->addDay(), 
        'end_date' => now()->addDays(5),
        'status' => 'active'
    ]);

    $response = $this->getJson('/api/player/playlist?device_id=SCR-EMPTY');

    $response->assertOk();
    expect($response->json('playlist'))->toBeEmpty();
});