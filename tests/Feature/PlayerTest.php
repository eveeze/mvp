<?php

use App\Models\Screen;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Media;

it('returns playlist for active campaign today', function () {
    // 1. Setup Screen
    $screen = Screen::factory()->create(['code' => 'SCREEN-001', 'is_active' => true]);
    
    // 2. Setup Campaign AKTIF HARI INI
    $user = User::factory()->create();
    $media = Media::factory()->create(['status' => 'completed', 'user_id' => $user->id]);
    
    $campaign = Campaign::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->toDateString(), // Hari ini
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'active'
    ]);
    
    $campaign->items()->create([
        'screen_id' => $screen->id,
        'media_id' => $media->id,
        'plays_per_day' => 100,
        'price_per_play' => 1000
    ]);

    // 3. Hit Endpoint Player
    $response = $this->getJson('/api/player/playlist?device_id=SCREEN-001');

    $response->assertOk()
        ->assertJsonStructure(['playlist' => [['url', 'title', 'duration']]]);
        
    // Pastikan media ada di playlist
    $playlist = $response->json('playlist');
    expect($playlist)->toHaveCount(1);
    expect($playlist[0]['media_id'])->toBe($media->id);
});

it('returns empty playlist if no active campaign', function () {
    $screen = Screen::factory()->create(['code' => 'SCREEN-002']);

    // Campaign masa depan (Besok)
    $campaign = Campaign::factory()->create([
        'start_date' => now()->addDay(), 
        'end_date' => now()->addDays(5),
        'status' => 'active'
    ]);
    // ... item created ...

    $response = $this->getJson('/api/player/playlist?device_id=SCREEN-002');

    $response->assertOk();
    expect($response->json('playlist'))->toBeEmpty();
});