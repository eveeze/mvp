<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\CampaignItem;
use App\Models\PlayerTelemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis; // [NEW]
use Carbon\Carbon;

class PlayerController extends Controller
{
    /**
     * 1. Get Playlist Harian
     */
    public function getPlaylist(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $screen = Screen::where('code', $request->device_id)->first();

        if (!$screen) return response()->json(['status' => 'error', 'message' => 'Device ID unregistered.'], 404);
        if (!$screen->is_active) return response()->json(['status' => 'error', 'message' => 'Screen suspended.'], 403);

        $screen->update(['is_online' => true, 'last_seen_at' => now()]);
        $today = Carbon::now();

        $campaignItems = CampaignItem::with(['media', 'campaign'])
            ->where('screen_id', $screen->id)
            ->whereHas('campaign', function ($query) use ($today) {
                $query->where('status', 'active')
                      ->whereDate('start_date', '<=', $today)
                      ->whereDate('end_date', '>=', $today);
            })
            ->whereHas('media', function ($query) {
                $query->where('moderation_status', 'approved')
                      ->where('status', 'completed');
            })
            ->get();

        $playlist = $campaignItems->map(function ($item) {
            return [
                'id'          => $item->id,
                'campaign_id' => $item->campaign_id,
                'media_id'    => $item->media_id,
                'title'       => $item->campaign->name,
                'url'         => $item->media->url, 
                'type'        => $item->media->type,
                'duration'    => $item->media->type === 'video' ? $item->media->duration : 10,
                'slots'       => $item->plays_per_day,
                'hash'        => md5($item->media->updated_at . $item->id),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'screen_name' => $screen->name,
                'location'    => $screen->location,
                'server_time' => now()->toIso8601String(),
                'refresh_interval' => 300,
            ],
            'playlist' => $playlist
        ]);
    }

    /**
     * 2. Telemetry (Heartbeat)
     */
    public function telemetry(Request $request)
    {
        $request->validate([
            'device_id'    => 'required|string|exists:screens,code',
            'cpu_usage'    => 'nullable|integer',
            'memory_usage' => 'nullable|integer',
            'temperature'  => 'nullable|numeric',
            'uptime_sec'   => 'nullable|integer',
            'app_version'  => 'nullable|string',
        ]);

        $screen = Screen::where('code', $request->device_id)->firstOrFail();
        $screen->update(['is_online' => true, 'last_seen_at' => now()]);

        PlayerTelemetry::create([
            'screen_id'    => $screen->id,
            'cpu_usage'    => $request->cpu_usage,
            'memory_usage' => $request->memory_usage,
            'temperature'  => $request->temperature,
            'uptime_sec'   => $request->uptime_sec,
            'app_version'  => $request->app_version,
            'recorded_at'  => now(),
        ]);

        return response()->json(['status' => 'ok', 'sync_needed' => false]);
    }

    /**
     * 3. Impression Log (Proof of Play) - [REFACTOR DAY 8: REDIS BUFFER]
     */
    public function storeImpression(Request $request)
    {
        $request->validate([
            'device_id'    => 'required|string|exists:screens,code',
            'media_id'     => 'required|exists:media,id',
            'played_at'    => 'required|date',
            'duration_sec' => 'required|integer|min:1',
        ]);

        $screen = Screen::where('code', $request->device_id)->firstOrFail();

        // [OPTIMASI] Push ke Redis List daripada Insert DB langsung
        // Ini membuat endpoint sangat ringan (<10ms)
        $logData = [
            'screen_id'    => $screen->id,
            'media_id'     => $request->media_id,
            'played_at'    => $request->played_at,
            'duration_sec' => $request->duration_sec,
            'created_at'   => now()->toDateTimeString(),
            'updated_at'   => now()->toDateTimeString(),
        ];

        try {
            Redis::rpush('impression_logs_queue', json_encode($logData));
        } catch (\Exception $e) {
            // Fallback jika Redis mati (sangat jarang): Log ke file atau DB langsung
            \Log::error('Redis Push Failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Logging failed'], 500);
        }

        return response()->json(['status' => 'buffered']);
    }
}