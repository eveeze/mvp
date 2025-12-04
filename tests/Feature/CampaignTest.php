<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Screen;
use App\Models\Media;
use App\Models\Campaign;
use App\Models\CampaignItem; // Import ini
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $this->user->id, 'balance' => 1000000]);

    $this->screen = Screen::factory()->create([
        'price_per_play' => 10000,
        'max_plays_per_day' => 10, // Kapasitas 10
        'max_duration_sec' => 60,
        'is_active' => true
    ]);

    $this->media = Media::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'duration' => 30
    ]);
});

it('can create campaign successfully', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Promo Sukses',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'media_id' => $this->media->id,
            'screens' => [
                ['id' => $this->screen->id, 'plays_per_day' => 5]
            ]
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('wallets', [
        'user_id' => $this->user->id,
        'balance' => 950000
    ]);
});

it('fails if wallet balance insufficient', function () {
    $this->user->wallet->update(['balance' => 0]);

    $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Promo Gagal',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'media_id' => $this->media->id,
            'screens' => [
                ['id' => $this->screen->id, 'plays_per_day' => 5]
            ]
        ])
        ->assertStatus(422);
});

it('fails if screen overbooked', function () {
    $targetDate = now()->addDays(2)->toDateString();

    // 1. Buat Campaign A (Habiskan 8 slot)
    $c1 = Campaign::factory()->create([
        'user_id' => $this->user->id,
        'start_date' => $targetDate,
        'end_date' => $targetDate,
        'status' => 'active'
    ]);
    
    // Create item manual biar pasti
    CampaignItem::create([
        'campaign_id' => $c1->id,
        'screen_id' => $this->screen->id,
        'media_id' => $this->media->id,
        'plays_per_day' => 8,
        'price_per_play' => 10000
    ]);

    // 2. Coba Buat Campaign B (Minta 5 slot lagi)
    // 8 + 5 = 13 > 10 (Gagal)
    $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Overbook',
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'media_id' => $this->media->id,
            'screens' => [
                ['id' => $this->screen->id, 'plays_per_day' => 5]
            ]
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['screens' => ["Screen '{$this->screen->name}' penuh/tidak cukup slot di tanggal tersebut. Sisa slot: 2."]]);
});

it('fails if media duration too long', function () {
    $this->media->update(['duration' => 90]); // Max 60

    $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Video Kepanjangan',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'media_id' => $this->media->id,
            'screens' => [
                ['id' => $this->screen->id, 'plays_per_day' => 1]
            ]
        ])
        ->assertStatus(422);
});