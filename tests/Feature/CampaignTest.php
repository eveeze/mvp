<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Screen;
use App\Models\Media;
use App\Models\Campaign;
use App\Models\RateCard;
use App\Models\CampaignItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $this->user->id, 'balance' => 5000000]);
    $this->media = Media::factory()->create(['user_id' => $this->user->id, 'status' => 'completed', 'duration' => 15]);
});

it('calculates cost using Rate Card when no overrides set', function () {
    RateCard::create(['hotel_star_rating' => 4, 'duration_days' => 1, 'base_price' => 50000]);
    
    $hotel = \App\Models\Hotel::factory()->create(['star_rating' => 4, 'price_override' => null]);
    $screen = Screen::factory()->create(['hotel_id' => $hotel->id, 'price_per_play' => null]);

    $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Rate Card Test',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'media_id' => $this->media->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 10]]
        ])
        ->assertCreated();

    $this->assertDatabaseHas('campaigns', ['total_cost' => 500000]);
});

it('fails if wallet balance insufficient', function () {
    $this->user->wallet->update(['balance' => 0]);
    $screen = Screen::factory()->create(['price_per_play' => 10000]);

    $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Gagal Bayar',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'media_id' => $this->media->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 1]]
        ])
        ->assertStatus(422);
});

it('fails if screen overbooked', function () {
    // 1. Setup Screen Kapasitas 10
    $screen = Screen::factory()->create(['max_plays_per_day' => 10]);
    $date = now()->addDays(2)->toDateString();

    // 2. Buat Campaign A (Penuh - 10 slot)
    // Kita create manual untuk memastikan data ada di DB
    $c = Campaign::create([
        'user_id' => $this->user->id,
        'name' => 'Existing Campaign',
        'start_date' => $date, 
        'end_date' => $date, 
        'status' => 'active',
        'total_cost' => 0
    ]);
    
    CampaignItem::create([
        'campaign_id' => $c->id, 
        'screen_id' => $screen->id, 
        'media_id' => $this->media->id, 
        'plays_per_day' => 10, 
        'price_per_play' => 1000
    ]);

    // 3. Coba Buat Campaign B (Minta 1 slot lagi)
    // Total 10 + 1 = 11 > 10 -> Harusnya Gagal (422)
    $response = $this->actingAs($this->user)
        ->postJson('/api/campaigns', [
            'name' => 'Overbook Request',
            'start_date' => $date,
            'end_date' => $date,
            'media_id' => $this->media->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 1]]
        ]);
        
    $response->assertStatus(422)
             ->assertJsonFragment(['screens' => ["Screen '{$screen->name}' penuh. Sisa slot: 0."]]);
});