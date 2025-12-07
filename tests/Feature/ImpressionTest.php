<?php

use App\Models\Screen;
use App\Models\Media;
use App\Jobs\ProcessImpressionLog;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('queues impression log when player reports playback', function () {
    Queue::fake(); // Mock Queue agar job tidak jalan beneran, cuma dicek

    $screen = Screen::factory()->create(['code' => 'SCR-PLAY-001']);
    $media = Media::factory()->create();

    $payload = [
        'device_id'    => 'SCR-PLAY-001',
        'media_id'     => $media->id,
        'played_at'    => now()->toIso8601String(),
        'duration_sec' => 15
    ];

    $this->postJson('/api/player/logs/impression', $payload)
         ->assertOk()
         ->assertJson(['status' => 'queued']);

    // Pastikan Job dengan data yang benar masuk antrian
    Queue::assertPushed(ProcessImpressionLog::class, function ($job) use ($media, $screen) {
        return $job->data['media_id'] === $media->id && 
               $job->data['screen_id'] === $screen->id;
    });
});