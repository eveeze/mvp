<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

class MidtransService
{
    public function __construct()
    {
        // Konfigurasi Midtrans Global
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Generate Snap Token untuk Frontend
     */
    public function createSnapToken(array $params)
    {
        return Snap::getSnapToken($params);
    }

    /**
     * Handle notifikasi webhook dari Midtrans
     */
    public function handleNotification()
    {
        // Midtrans mengirim payload via PHP input stream
        // Class Notification otomatis memvalidasi Signature Key di dalamnya
        try {
            $notification = new Notification();
            return $notification;
        } catch (\Exception $e) {
            \Log::error('Midtrans Notification Error: ' . $e->getMessage());
            return null;
        }
    }
}