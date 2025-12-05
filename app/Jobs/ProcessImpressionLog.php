<?php

namespace App\Jobs;

use App\Models\ImpressionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImpressionLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Data impression mentah dari request.
     * [UPDATE] Ubah ke PUBLIC agar bisa diakses saat testing (Queue::assertPushed)
     */
    public function __construct(public array $data)
    {
    }

    public function handle(): void
    {
        ImpressionLog::create([
            'screen_id'    => $this->data['screen_id'],
            'media_id'     => $this->data['media_id'],
            'played_at'    => $this->data['played_at'],
            'duration_sec' => $this->data['duration_sec'],
        ]);
    }
}