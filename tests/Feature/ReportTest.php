<?php

use App\Models\User;
use App\Models\Campaign;
use App\Models\ImpressionLog;
use App\Models\Screen;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can generate campaign performance report for advertiser', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    $screen = Screen::factory()->create();
    $media = Media::factory()->create();
    
    // 1. Buat Campaign Aktif
    $campaign = Campaign::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(1),
        'status' => 'active'
    ]);
    
    $campaign->items()->create([
        'screen_id' => $screen->id,
        'media_id' => $media->id,
        'plays_per_day' => 10,
        'price_per_play' => 100
    ]);

    // 2. Seed Data Dummy Impression Logs
    ImpressionLog::create(['screen_id' => $screen->id, 'media_id' => $media->id, 'played_at' => now()->subDay(), 'duration_sec' => 15]);
    ImpressionLog::create(['screen_id' => $screen->id, 'media_id' => $media->id, 'played_at' => now()->subDay(), 'duration_sec' => 15]);
    ImpressionLog::create(['screen_id' => $screen->id, 'media_id' => $media->id, 'played_at' => now(), 'duration_sec' => 15]);

    // 3. Hit Endpoint Report
    $this->actingAs($user)
        ->getJson("/api/reports/campaign/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.campaign_name', $campaign->name)
        ->assertJsonPath('data.summary.total_impressions', 3)
        ->assertJsonPath('data.breakdown.0.actual_plays', 3);
});

it('allows superadmin to view occupancy report', function () {
    $admin = User::factory()->superAdmin()->create();
    $screen = Screen::factory()->create(['max_plays_per_day' => 10]);
    $media = Media::factory()->create();

    // Campaign pakai 5 slot dari 10
    $c = Campaign::factory()->create(['status' => 'active', 'start_date' => now(), 'end_date' => now()]);
    
    $c->items()->create([
        'screen_id' => $screen->id, 
        'media_id' => $media->id, 
        'plays_per_day' => 5, 
        'price_per_play' => 100
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/reports/occupancy?date=' . now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.occupancy_summary.rate', 50); // Integer 50
});

it('allows superadmin to view revenue report', function () {
    $admin = User::factory()->superAdmin()->create();
    $advertiser = User::factory()->create();

    // 2 Campaign @ 100.000
    Campaign::factory()->count(2)->create([
        'user_id' => $advertiser->id,
        'status' => 'active',
        'total_cost' => 100000,
        'created_at' => now()
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/reports/revenue');

    $response->assertOk()
        ->assertJsonCount(1, 'data.top_advertisers');

    // [FIX] Ambil nilai revenue dan cek secara manual dengan casting float
    // Ini menangani kasus database return string "200000.00" vs int 200000
    $monthlyRevenue = $response->json('data.revenue.monthly');
    expect((float) $monthlyRevenue)->toBe((float) 200000);
});