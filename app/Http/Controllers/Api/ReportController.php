<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ImpressionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get Campaign Performance Report
     * GET /api/reports/campaign/{id}
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();

        // 1. Ambil Campaign & Validasi Kepemilikan
        $campaign = Campaign::with(['items.screen.hotel', 'items.media'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // 2. Kumpulkan Media ID yang dipakai di campaign ini
        // (Biasanya 1 campaign = 1 media, tapi coding ini support multi-media)
        $mediaIds = $campaign->items->pluck('media_id')->unique();
        $screenIds = $campaign->items->pluck('screen_id')->unique();

        // 3. Query Agregasi Impression Log (Heavy Query Optimized)
        // Kita cari log yang match Media + Screen + Range Tanggal Campaign
        $stats = ImpressionLog::query()
            ->whereIn('media_id', $mediaIds)
            ->whereIn('screen_id', $screenIds)
            ->whereBetween('played_at', [
                $campaign->start_date->startOfDay(), 
                $campaign->end_date->endOfDay()
            ])
            ->select([
                'screen_id',
                DB::raw('COUNT(*) as total_impressions'),
                DB::raw('SUM(duration_sec) as total_duration_sec')
            ])
            ->groupBy('screen_id')
            ->get()
            ->keyBy('screen_id');

        // 4. Format Laporan per Screen
        $breakdown = $campaign->items->map(function ($item) use ($stats, $campaign) {
            $stat = $stats->get($item->screen_id);
            $impressions = $stat ? (int) $stat->total_impressions : 0;
            $duration = $stat ? (int) $stat->total_duration_sec : 0;

            // Hitung Target (Total hari x Slot per hari)
            $daysRunning = max(1, now()->diffInDays($campaign->start_date, false)); // Hari berjalan
            $totalDays = $campaign->start_date->diffInDays($campaign->end_date) + 1;
            
            $targetImpressions = $item->plays_per_day * $totalDays;
            $realizationRate = $targetImpressions > 0 ? ($impressions / $targetImpressions) * 100 : 0;

            return [
                'screen_name' => $item->screen->name,
                'location'    => $item->screen->hotel->name . ' - ' . $item->screen->location,
                'target_plays' => $targetImpressions,
                'actual_plays' => $impressions,
                'total_duration_sec' => $duration,
                'realization_percentage' => round($realizationRate, 2),
                'status' => $item->screen->is_online ? 'Online' : 'Offline'
            ];
        });

        // 5. Summary Global
        $totalImpressions = $stats->sum('total_impressions');
        $totalDuration = $stats->sum('total_duration_sec');

        return response()->json([
            'status' => 'success',
            'data' => [
                'campaign_name' => $campaign->name,
                'period' => $campaign->start_date->format('Y-m-d') . ' - ' . $campaign->end_date->format('Y-m-d'),
                'summary' => [
                    'total_impressions' => (int) $totalImpressions,
                    'total_duration_minutes' => round($totalDuration / 60, 1),
                    'total_cost' => $campaign->total_cost,
                ],
                'breakdown' => $breakdown
            ]
        ]);
    }
}