<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnapToken(array $params)
    {
        return Snap::getSnapToken($params);
    }

    /**
     * Handle Notification dengan Validasi Ketat (Production Ready)
     */
    public function handleNotification()
    {
        try {
            // Constructor Notification() otomatis memvalidasi Signature Key.
            // Jika payload dipalsukan atau signature salah, ini akan throw Exception.
            $notification = new Notification();
            
            return $notification;
        } catch (\Exception $e) {
            // Log error keamanan penting
            Log::warning("Midtrans Security Alert: Invalid Signature or Payload. IP: " . request()->ip() . " Error: " . $e->getMessage());
            
            // Return null agar Controller merespons 403 Forbidden
            return null;
        }
    }
}