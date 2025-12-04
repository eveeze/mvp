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

/*
|--------------------------------------------------------------------------
| Public Routes (Tanpa Login User)
|--------------------------------------------------------------------------
*/

// Auth User
Route::post('/register', [AuthController::class, 'register']); // Method sudah diganti jadi 'register' di Controller
Route::post('/login', [AuthController::class, 'login']);

// Midtrans Callback
Route::post('/callback/midtrans', [CallbackController::class, 'handleMidtrans']);

// === PLAYER / IOT ROUTES ===
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
    
    // User Info & Logout
    Route::get('/user', [AuthController::class, 'me']); // Saya arahkan ke method 'me' biar rapi
    Route::post('/logout', [AuthController::class, 'logout']); // Method ini sekarang sudah ada

    // === ROLE: SUPER ADMIN ===
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
    });

    // === ROLE: ADVERTISER ===
    Route::middleware('role:advertiser')->group(function () {
        
        // 1. Finance
        Route::post('/deposits', [DepositController::class, 'store']);
        Route::get('/deposits', [DepositController::class, 'index']);
        
        // 2. Asset Management
        Route::post('/media', [MediaController::class, 'store']);
        Route::get('/media', [MediaController::class, 'index']);
        
        // 3. Campaign / Booking
        Route::apiResource('campaigns', CampaignController::class)
             ->only(['index', 'store', 'show']);
             
        // Public Screens list for Advertiser
        Route::get('/public/screens', [ScreenController::class, 'index']); 
    });

});