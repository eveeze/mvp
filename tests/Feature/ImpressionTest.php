<?php

use App\Models\Screen;
use App\Models\Media;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pushes impression log to redis buffer', function () {
    // 1. Mock Redis
    // Kita pastikan method 'rpush' dipanggil dengan parameter yang benar
    Redis::shouldReceive('rpush')
        ->once()
        ->withArgs(function ($key, $value) {
            if ($key !== 'impression_logs_queue') return false;
            
            $data = json_decode($value, true);
            return isset($data['screen_id']) && isset($data['media_id']);
        });

    $screen = Screen::factory()->create(['code' => 'SCR-REDIS']);
    $media = Media::factory()->create();

    $payload = [
        'device_id'    => 'SCR-REDIS',
        'media_id'     => $media->id,
        'played_at'    => now()->toIso8601String(),
        'duration_sec' => 15
    ];

    $this->postJson('/api/player/logs/impression', $payload)
         ->assertOk()
         ->assertJson(['status' => 'buffered']);
});