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
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AdminMediaController;

/*
|--------------------------------------------------------------------------
| Public Routes (Tanpa Login User)
|--------------------------------------------------------------------------
*/

// Auth User
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Midtrans Callback
Route::post('/callback/midtrans', [CallbackController::class, 'handleMidtrans']);

// === PLAYER / IOT ROUTES (Device Auth via Device ID) ===
Route::prefix('player')->group(function () {
    
    // 1. Tarik Playlist Harian
    Route::get('/playlist', [PlayerController::class, 'getPlaylist']);
    
    // 2. Lapor Kesehatan (Heartbeat/Telemetry)
    Route::post('/telemetry', [PlayerController::class, 'telemetry']);
    
    // 3. Lapor Bukti Tayang (Proof of Play)
    Route::post('/logs/impression', [PlayerController::class, 'storeImpression']);
    
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Harus Login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Global
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [DashboardController::class, 'updateProfile']);

    // Super Admin
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
        
        Route::get('/admin/media', [AdminMediaController::class, 'index']);
        Route::put('/admin/media/{id}/approve', [AdminMediaController::class, 'approve']);
        Route::put('/admin/media/{id}/reject', [AdminMediaController::class, 'reject']);
    });

    // Advertiser
    Route::middleware('role:advertiser')->group(function () {
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::post('/deposits', [DepositController::class, 'store']);
        Route::get('/deposits', [DepositController::class, 'index']);
        Route::post('/media', [MediaController::class, 'store']);
        Route::get('/media', [MediaController::class, 'index']);
        Route::apiResource('campaigns', CampaignController::class)->only(['index', 'store', 'show']);
        Route::get('/public/screens', [ScreenController::class, 'index']); 
    });

});