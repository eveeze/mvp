<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\ScreenController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\PlayerController; // Controller Baru

/*
|--------------------------------------------------------------------------
| Public Routes (Tanpa Login User)
|--------------------------------------------------------------------------
*/

// Auth User
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Midtrans Callback (Wajib Public agar bisa ditembak Midtrans)
Route::post('/callback/midtrans', [CallbackController::class, 'handleMidtrans']);

// === PLAYER / IOT ROUTES ===
// Diakses oleh TV/Videotron (Menggunakan Device ID)
Route::prefix('player')->group(function () {
    Route::get('/playlist', [PlayerController::class, 'getPlaylist']);
    Route::post('/heartbeat', [PlayerController::class, 'heartbeat']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Harus Login: Admin / Advertiser)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // User Info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // === ROLE: SUPER ADMIN ===
    Route::middleware('role:super_admin')->group(function () {
        // Manajemen Hotel & Screen
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
    });

    // === ROLE: ADVERTISER ===
    Route::middleware('role:advertiser')->group(function () {
        
        // 1. Finance (Deposit & Wallet)
        Route::post('/deposits', [DepositController::class, 'store']);
        Route::get('/deposits', [DepositController::class, 'index']);
        
        // 2. Asset Management (Video Upload)
        Route::post('/media', [MediaController::class, 'store']); // Upload Video
        Route::get('/media', [MediaController::class, 'index']);
        
        // 3. Campaign / Booking (Core Business)
        Route::apiResource('campaigns', CampaignController::class)
             ->only(['index', 'store', 'show']);
             
        // Advertiser bisa melihat list screen untuk memilih (Read Only)
        Route::get('/public/screens', [ScreenController::class, 'index']); 
        // ^ Note: Logic "Read Only Screen" bisa dibuat method khusus di ScreenController
        // atau gunakan endpoint hotels.screens jika diizinkan.
    });

});