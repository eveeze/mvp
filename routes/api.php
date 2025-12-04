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
use App\Http\Controllers\Api\AdminMediaController; // [BARU] Import Controller Moderasi

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
    
    // Global User Info & Logout
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [DashboardController::class, 'updateProfile']);

    // === ROLE: SUPER ADMIN ===
    Route::middleware('role:super_admin')->group(function () {
        // Manajemen Hotel & Screen
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
        
        // [BARU] Media Moderation Routes
        Route::get('/admin/media', [AdminMediaController::class, 'index']); // List pending
        Route::put('/admin/media/{id}/approve', [AdminMediaController::class, 'approve']); // Approve
        Route::put('/admin/media/{id}/reject', [AdminMediaController::class, 'reject']); // Reject
    });

    // === ROLE: ADVERTISER ===
    Route::middleware('role:advertiser')->group(function () {
        
        // Dashboard Stats
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // 1. Finance (Deposit & Wallet)
        Route::post('/deposits', [DepositController::class, 'store']);
        Route::get('/deposits', [DepositController::class, 'index']);
        
        // 2. Asset Management (Video/Image Upload)
        Route::post('/media', [MediaController::class, 'store']); // Upload
        Route::get('/media', [MediaController::class, 'index']);  // List Status
        
        // 3. Campaign / Booking (Core Business)
        Route::apiResource('campaigns', CampaignController::class)
             ->only(['index', 'store', 'show']);
             
        // Advertiser bisa melihat list screen untuk memilih (Read Only)
        Route::get('/public/screens', [ScreenController::class, 'index']); 
    });

});