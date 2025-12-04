<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\CampaignItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlayerController extends Controller
{
    /**
     * Endpoint Utama: Player menarik Playlist Harian.
     * GET /api/player/playlist?device_id=TV-LOBBY-01
     */
    public function getPlaylist(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        // 1. Cari Screen berdasarkan Device Code
        $screen = Screen::where('code', $request->device_id)->first();

        // Jika device tidak dikenali
        if (!$screen) {
            return response()->json([
                'status' => 'error',
                'message' => 'Device ID unregistered.'
            ], 404);
        }

        // 2. Cek Status Screen (Admin Block)
        if (!$screen->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Screen suspended.',
                'data' => [] 
            ], 403);
        }

        // Update heartbeat (status online)
        $screen->update(['is_online' => true]);

        // 3. Query Iklan yang TAYANG HARI INI
        $today = Carbon::now();

        // Logic: Ambil item kampanye yang:
        // - Screen-nya cocok
        // - Campaign induknya status 'active'
        // - Hari ini berada di antara start_date dan end_date
        $campaignItems = CampaignItem::with(['media', 'campaign'])
            ->where('screen_id', $screen->id)
            ->whereHas('campaign', function ($query) use ($today) {
                $query->where('status', 'active')
                      ->whereDate('start_date', '<=', $today)
                      ->whereDate('end_date', '>=', $today);
            })
            ->get();

        // 4. Format JSON untuk Player
        $playlist = $campaignItems->map(function ($item) {
            // Skip jika media belum siap (masih processing) atau error
            if (!$item->media || $item->media->status !== 'completed') {
                return null;
            }

            return [
                'campaign_id' => $item->campaign_id,
                'media_id'    => $item->media_id,
                'title'       => $item->campaign->name,
                
                // URL ini otomatis menunjuk ke file .m3u8 di S3/Storage (sesuai Model Media)
                'url'         => $item->media->url, 
                
                'duration'    => $item->media->duration,
                'type'        => 'video', 
                'slots'       => $item->plays_per_day,
                'hash'        => md5($item->media->updated_at . $item->id), // Cache buster
            ];
        })->filter()->values(); // Bersihkan nilai null

        // 5. Response
        return response()->json([
            'status' => 'success',
            'meta' => [
                'screen_name' => $screen->name,
                'location'    => $screen->location,
                'server_time' => now()->toIso8601String(),
                'refresh_interval' => 300, // Player cek lagi tiap 5 menit
            ],
            'playlist' => $playlist
        ]);
    }

    /**
     * Heartbeat: Agar admin tau layar hidup tanpa fetch playlist berat.
     * POST /api/player/heartbeat
     */
    public function heartbeat(Request $request)
    {
        $request->validate(['device_id' => 'required|string']);

        $screen = Screen::where('code', $request->device_id)->first();
        
        if ($screen) {
            $screen->update(['is_online' => true]);
        }

        return response()->json(['status' => 'ok']);
    }
}