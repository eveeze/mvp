<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// [BARU] Jadwalkan Batch Processing
// Jalankan setiap menit untuk memindahkan data dari Redis ke DB
Schedule::command('impression:process-batch')->everyMinute();

// Opsional: Snapshot Daily Revenue / Cleanup
// Schedule::command('reports:snapshot')->daily();