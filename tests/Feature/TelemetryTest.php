<?php

use App\Models\Screen;
use App\Models\PlayerTelemetry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates screen status and logs telemetry', function () {
    $screen = Screen::factory()->create([
        'code' => 'SCR-TEST-001',
        'is_online' => false,
        'last_seen_at' => now()->subDays(1)
    ]);

    $payload = [
        'device_id'    => 'SCR-TEST-001',
        'cpu_usage'    => 45,
        'memory_usage' => 2048,
        'temperature'  => 55.5,
        'uptime_sec'   => 3600,
        'app_version'  => 'v1.0.0'
    ];

    $this->postJson('/api/player/telemetry', $payload)
         ->assertOk()
         ->assertJson(['status' => 'ok']);

    // 1. Cek Screen Status Terupdate
    $screen->refresh();
    expect($screen->is_online)->toBeTrue();
    // Cek last_seen_at updated (dalam 5 detik terakhir)
    expect($screen->last_seen_at->diffInSeconds(now()))->toBeLessThan(5);

    // 2. Cek Log Telemetry Tersimpan
    $this->assertDatabaseHas('player_telemetries', [
        'screen_id' => $screen->id,
        'cpu_usage' => 45,
        'app_version' => 'v1.0.0'
    ]);
});

it('rejects telemetry from unknown device', function () {
    $this->postJson('/api/player/telemetry', ['device_id' => 'UNKNOWN-ID'])
         ->assertStatus(422); // Validation error (exists)
});