<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Screen;
use App\Models\User;
use App\Mail\ScreenOfflineAlert; // Import
use Illuminate\Support\Facades\Mail; // Import

class MonitorScreens extends Command
{
    protected $signature = 'monitor:screens';
    protected $description = 'Check for offline screens and alert admin';

    public function handle()
    {
        $offlineScreens = Screen::where('is_active', true)
            ->where('is_online', true)
            ->where('last_seen_at', '<', now()->subHour())
            ->get();

        if ($offlineScreens->isNotEmpty()) {
            Screen::whereIn('id', $offlineScreens->pluck('id'))->update(['is_online' => false]);
            
            $adminEmail = User::where('role', 'super_admin')->value('email') ?? 'admin@eveeze.com';
            
            // [KIRIM EMAIL]
            Mail::to($adminEmail)->queue(new ScreenOfflineAlert($offlineScreens));
            
            $this->info("Alert sent for {$offlineScreens->count()} screens.");
        } else {
            $this->info("All screens healthy.");
        }
    }
}