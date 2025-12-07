<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Campaign;
use App\Models\CampaignItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    /**
     * Laporan Okupansi Layar (Seberapa penuh slot iklan?)
     * GET /api/admin/reports/occupancy
     */
    public function occupancy(Request $request)
    {
        // Ambil tanggal filter atau default hari ini
        $date = $request->input('date', now()->toDateString());

        // 1. Ambil semua Hotel beserta Screen-nya
        $hotels = Hotel::with(['screens'])->get();

        // 2. Hitung Slot Terpakai per Screen untuk Tanggal tersebut
        // Query ini menghitung total 'plays_per_day' dari campaign aktif di tanggal tsb
        $usageStats = CampaignItem::whereHas('campaign', function ($q) use ($date) {
                $q->where('status', 'active')
                  ->whereDate('start_date', '<=', $date)
                  ->whereDate('end_date', '>=', $date);
            })
            ->select('screen_id', DB::raw('SUM(plays_per_day) as used_slots'))
            ->groupBy('screen_id')
            ->get()
            ->pluck('used_slots', 'screen_id');

        // 3. Format Data Laporan
        $report = $hotels->map(function ($hotel) use ($usageStats) {
            $totalScreens = $hotel->screens->count();
            $totalCapacity = $hotel->screens->sum('max_plays_per_day');
            $totalUsed = 0;

            $screensDetail = $hotel->screens->map(function ($screen) use ($usageStats, &$totalUsed) {
                $used = $usageStats[$screen->id] ?? 0;
                $totalUsed += $used;
                $capacity = $screen->max_plays_per_day;
                
                return [
                    'name' => $screen->name,
                    'capacity' => $capacity,
                    'used' => (int) $used,
                    'occupancy_rate' => $capacity > 0 ? round(($used / $capacity) * 100, 1) : 0
                ];
            });

            return [
                'hotel_name' => $hotel->name,
                'total_screens' => $totalScreens,
                'occupancy_summary' => [
                    'capacity' => (int) $totalCapacity,
                    'used' => (int) $totalUsed,
                    'rate' => $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100, 1) : 0
                ],
                'screens' => $screensDetail
            ];
        });

        return response()->json([
            'status' => 'success',
            'meta' => ['date' => $date],
            'data' => $report
        ]);
    }

    /**
     * Laporan Pendapatan (Revenue)
     * GET /api/admin/reports/revenue
     */
    public function revenue(Request $request)
    {
        // Filter Tahun/Bulan
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // 1. Total Revenue Bulan Ini (Berdasarkan Campaign Created/Start)
        $monthlyRevenue = Campaign::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('status', ['active', 'finished']) // Hanya hitung yang jadi uang
            ->sum('total_cost');

        // 2. Total Revenue Tahunan
        $yearlyRevenue = Campaign::whereYear('created_at', $year)
            ->whereIn('status', ['active', 'finished'])
            ->sum('total_cost');

        // 3. Top 5 Advertiser (Spender Terbesar)
        $topSpenders = Campaign::whereIn('status', ['active', 'finished'])
            ->select('user_id', DB::raw('SUM(total_cost) as total_spent'))
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => "$year-$month",
                'revenue' => [
                    'monthly' => $monthlyRevenue,
                    'yearly' => $yearlyRevenue
                ],
                'top_advertisers' => $topSpenders
            ]
        ]);
    }
}