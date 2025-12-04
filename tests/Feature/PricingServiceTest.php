<?php

namespace Tests\Unit;

use App\Models\Hotel;
use App\Models\Screen;
use App\Models\RateCard;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PricingService();
    }

    // 1. Prioritas Tertinggi: Override di Screen
    public function test_it_uses_screen_price_if_set()
    {
        $screen = Screen::factory()->create([
            'price_per_play' => 50000,
        ]);
        
        // Walaupun hotel punya override, screen tetap menang
        $screen->hotel->update(['price_override' => 20000]);

        // Hitung: 1 Hari, 1 Play
        $cost = $this->service->calculatePrice($screen, 1, 1);

        $this->assertEquals(50000, $cost);
    }

    // 2. Prioritas Kedua: Override di Hotel
    public function test_it_uses_hotel_override_if_screen_price_null()
    {
        $hotel = Hotel::factory()->create([
            'price_override' => 75000,
        ]);
        
        $screen = Screen::factory()->create([
            'hotel_id' => $hotel->id,
            'price_per_play' => null, // Tidak ada harga spesifik
        ]);

        $cost = $this->service->calculatePrice($screen, 1, 1);

        $this->assertEquals(75000, $cost);
    }

    // 3. Prioritas Ketiga: Rate Card (Berdasarkan Bintang)
    public function test_it_uses_rate_card_based_on_star_rating()
    {
        // Rate Card: Bintang 5, Paket 1 Hari = 100.000
        RateCard::create([
            'hotel_star_rating' => 5,
            'duration_days' => 1,
            'base_price' => 100000
        ]);

        $hotel = Hotel::factory()->create([
            'star_rating' => 5,
            'price_override' => null,
        ]);

        $screen = Screen::factory()->create([
            'hotel_id' => $hotel->id,
            'price_per_play' => null,
        ]);

        $cost = $this->service->calculatePrice($screen, 1, 1);

        $this->assertEquals(100000, $cost);
    }

    // 4. Fallback Default (Jika semua kosong)
    public function test_it_uses_default_price_if_no_rate_configured()
    {
        $hotel = Hotel::factory()->create([
            'star_rating' => 3,
            'price_override' => null,
        ]);

        $screen = Screen::factory()->create([
            'hotel_id' => $hotel->id,
            'price_per_play' => null,
        ]);

        // Rate Card kosong, Override kosong
        // Default di service biasanya 10.000
        $cost = $this->service->calculatePrice($screen, 1, 1);

        $this->assertEquals(10000, $cost);
    }

    // 5. Test Perhitungan Durasi & Frekuensi
    public function test_it_calculates_total_correctly_multiple_days_and_plays()
    {
        $screen = Screen::factory()->create(['price_per_play' => 10000]);

        // 5 Hari, 10 Play per hari
        // Total = 10.000 * 5 * 10 = 500.000
        $cost = $this->service->calculatePrice($screen, 5, 10);

        $this->assertEquals(500000, $cost);
    }
}