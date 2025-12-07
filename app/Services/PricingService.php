<?php

namespace App\Services;

use App\Models\Screen;
use App\Models\RateCard;

class PricingService
{
    public function calculatePrice(Screen $screen, int $days, int $playsPerDay): float
    {
        // 1. Cek Override Level Screen
        if ($screen->price_per_play !== null && $screen->price_per_play > 0) {
            return $screen->price_per_play * $playsPerDay * $days;
        }

        // 2. Cek Override Level Hotel
        if ($screen->hotel->price_override !== null && $screen->hotel->price_override > 0) {
            return $screen->hotel->price_override * $playsPerDay * $days;
        }

        // 3. Cek Rate Card (Berdasarkan Bintang Hotel)
        $rate = RateCard::where('hotel_star_rating', $screen->hotel->star_rating)
            ->where('duration_days', '<=', $days)
            ->orderByDesc('duration_days')
            ->first();

        if ($rate) {
            $dailyRate = $rate->base_price / $rate->duration_days;
            // [FIX] Wajib dikali playsPerDay
            return $dailyRate * $days * $playsPerDay; 
        }

        // 4. Default Fallback
        return 10000 * $playsPerDay * $days;
    }
}