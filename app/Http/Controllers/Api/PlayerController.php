<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\CampaignItem;
use App\Models\PlayerTelemetry;
use App\Jobs\ProcessImpressionLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlayerController extends Controller
{
    /**
     * 1. Get Playlist Harian
     * Mengambil iklan yang harus tayang HARI INI di layar ini.
     */
    public function getPlaylist(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $screen = Screen::where('code', $request->device_id)->first();

        if (!$screen) {
            return response()->json(['status' => 'error', 'message' => 'Device ID unregistered.'], 404);
        }

        if (!$screen->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Screen suspended.'], 403);
        }

        // Update status online (Basic Heartbeat)
        $screen->update(['is_online' => true, 'last_seen_at' => now()]);

        $today = Carbon::now();

        // Query Item Kampanye yang VALID:
        // 1. Screen cocok
        // 2. Campaign status ACTIVE (Bukan pending/rejected)
        // 3. Media moderasi APPROVED
        // 4. Tanggal masuk range
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

        // Format JSON Ringan untuk Player
        $playlist = $campaignItems->map(function ($item) {
            return [
                'id'          => $item->id, // Campaign Item ID (untuk tracking log)
                'campaign_id' => $item->campaign_id,
                'media_id'    => $item->media_id,
                'title'       => $item->campaign->name,
                'url'         => $item->media->url, 
                'type'        => $item->media->type, // video/image
                'duration'    => $item->media->type === 'video' ? $item->media->duration : 10, // Default image 10s
                'hash'        => md5($item->media->updated_at . $item->id),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'screen_name' => $screen->name,
                'location'    => $screen->location,
                'server_time' => now()->toIso8601String(),
                'refresh_interval' => 300, // Cek playlist tiap 5 menit
            ],
            'playlist' => $playlist
        ]);
    }

    /**
     * 2. Telemetry (Heartbeat Canggih)
     * Player mengirim status kesehatan perangkat.
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

        // Update status Screen utama
        $screen->update([
            'is_online' => true,
            'last_seen_at' => now()
        ]);

        // Simpan Log Telemetry (History Kesehatan)
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
     * 3. Impression Log (Proof of Play)
     * Player melapor bahwa iklan sudah diputar.
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

        // Masukkan ke Queue agar response cepat dan DB tidak lock
        ProcessImpressionLog::dispatch([
            'screen_id'    => $screen->id,
            'media_id'     => $request->media_id,
            'played_at'    => $request->played_at,
            'duration_sec' => $request->duration_sec,
        ]);

        return response()->json(['status' => 'queued']);
    }
}