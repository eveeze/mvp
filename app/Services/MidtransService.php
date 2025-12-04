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
     * Handle notifikasi webhook dari Midtrans.
     * Support Mode Development (Bypass Signature).
     */
    public function handleNotification()
    {
        try {
            // Coba validasi resmi Midtrans
            // Ini akan memvalidasi Signature Key secara otomatis
            $notification = new Notification();
            return $notification;
        } catch (\Exception $e) {
            // [DEV HELPER] Jika validasi gagal TAPI kita sedang di local/testing
            // Kita bypass validasi agar bisa simulasi lewat Postman tanpa Signature Key
            if (app()->environment(['local', 'testing', 'development'])) {
                \Log::warning("Midtrans Signature Validation Bypassed (Dev Mode): " . $e->getMessage());
                
                // Ambil raw JSON body dari Postman dan jadikan object standar
                return json_decode(file_get_contents('php://input'));
            }

            // Jika di Production, error ini fatal (potensi serangan hacker), jadi return null
            \Log::error('Midtrans Notification Error: ' . $e->getMessage());
            return null;
        }
    }
}