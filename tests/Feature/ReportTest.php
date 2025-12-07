<?php

use App\Models\User;
use App\Models\Campaign;
use App\Models\ImpressionLog;
use App\Models\Screen;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('can generate campaign performance report for advertiser', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    $screen = Screen::factory()->create();
    $media = Media::factory()->create();
    
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

    ImpressionLog::create(['screen_id' => $screen->id, 'media_id' => $media->id, 'played_at' => now()->subDay(), 'duration_sec' => 15]);
    ImpressionLog::create(['screen_id' => $screen->id, 'media_id' => $media->id, 'played_at' => now()->subDay(), 'duration_sec' => 15]);
    ImpressionLog::create(['screen_id' => $screen->id, 'media_id' => $media->id, 'played_at' => now(), 'duration_sec' => 15]);

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

    $c = Campaign::factory()->create(['status' => 'active', 'start_date' => now(), 'end_date' => now()]);
    $c->items()->create(['screen_id' => $screen->id, 'media_id' => $media->id, 'plays_per_day' => 5, 'price_per_play' => 100]);

    $this->actingAs($admin)
        ->getJson('/api/admin/reports/occupancy?date=' . now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.occupancy_summary.rate', 50);
});

it('allows superadmin to view revenue report', function () {
    $admin = User::factory()->superAdmin()->create();
    $advertiser = User::factory()->create();

    Campaign::factory()->count(2)->create([
        'user_id' => $advertiser->id,
        'status' => 'active',
        'total_cost' => 100000,
        'created_at' => now()
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/reports/revenue');

    // [FIX] Validasi manual nilai revenue agar aman dari tipe data
    $response->assertOk()
        ->assertJsonCount(1, 'data.top_advertisers');
        
    $monthly = $response->json('data.revenue.monthly');
    // Memastikan nilainya setara dengan 200000 (baik string maupun int)
    expect((float)$monthly)->toBe((float)200000);
});