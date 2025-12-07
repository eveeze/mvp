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

// Helper function untuk setup user & media (agar tidak duplikasi kode)
function setupAdvertiserWithMedia() {
    $user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $user->id, 'balance' => 5000000]);
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'moderation_status' => 'approved',
        'duration' => 15
    ]);
    
    return [$user, $media];
}

// Test 1: Sukses (Rate Card)
it('calculates cost using Rate Card when no overrides set', function () {
    [$user, $media] = setupAdvertiserWithMedia();

    RateCard::create(['hotel_star_rating' => 4, 'duration_days' => 1, 'base_price' => 50000]);
    
    $hotel = \App\Models\Hotel::factory()->create(['star_rating' => 4, 'price_override' => null]);
    $screen = Screen::factory()->create(['hotel_id' => $hotel->id, 'price_per_play' => null]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'name' => 'Rate Card Test',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'media_id' => $media->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 10]]
        ])
        ->assertCreated();

    $this->assertDatabaseHas('campaigns', ['total_cost' => 500000]);
});

// Test 2: Gagal Saldo
it('fails if wallet balance insufficient', function () {
    [$user, $media] = setupAdvertiserWithMedia();
    
    // Kuras Saldo
    $user->wallet->update(['balance' => 0]);
    
    $screen = Screen::factory()->create(['price_per_play' => 10000]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'name' => 'Gagal Bayar',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'media_id' => $media->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 1]]
        ])
        ->assertStatus(422);
});

// Test 3: Gagal Overbook
it('fails if screen overbooked', function () {
    [$user, $media] = setupAdvertiserWithMedia();

    $screen = Screen::factory()->create(['max_plays_per_day' => 10]);
    $date = now()->addDays(2)->toDateString();

    // Campaign A (Penuh)
    $c = Campaign::factory()->create(['start_date' => $date, 'end_date' => $date, 'status' => 'active']);
    CampaignItem::create([
        'campaign_id' => $c->id, 'screen_id' => $screen->id, 
        'media_id' => $media->id, 'plays_per_day' => 10, 'price_per_play' => 1000
    ]);

    // Campaign B (Minta 1 lagi)
    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'name' => 'Overbook',
            'start_date' => $date,
            'end_date' => $date,
            'media_id' => $media->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 1]]
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['screens' => ["Screen '{$screen->name}' penuh. Sisa slot: 0."]]);
});

// Test 4: Gagal jika Media Belum Approved
it('fails to create campaign if media is not approved', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    Wallet::create(['user_id' => $user->id, 'balance' => 5000000]);

    // Buat media status pending
    $pendingMedia = Media::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'moderation_status' => 'pending' 
    ]);

    $screen = Screen::factory()->create(['price_per_play' => 10000]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'name' => 'Illegal Campaign',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'media_id' => $pendingMedia->id,
            'screens' => [['id' => $screen->id, 'plays_per_day' => 10]]
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Media belum disetujui oleh Admin.']);
});