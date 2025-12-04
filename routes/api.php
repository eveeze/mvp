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
use App\Http\Controllers\Api\DashboardController; // Baru

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/callback/midtrans', [CallbackController::class, 'handleMidtrans']);

// === PLAYER / IOT ROUTES (Device) ===
Route::prefix('player')->group(function () {
    Route::get('/playlist', [PlayerController::class, 'getPlaylist']);
    Route::post('/heartbeat', [PlayerController::class, 'heartbeat']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Login Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Global User Routes
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [DashboardController::class, 'updateProfile']);

    // === ROLE: SUPER ADMIN ===
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
    });

    // === ROLE: ADVERTISER ===
    Route::middleware('role:advertiser')->group(function () {
        
        // Dashboard Stats
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Finance
        Route::post('/deposits', [DepositController::class, 'store']);
        Route::get('/deposits', [DepositController::class, 'index']);
        
        // Assets
        Route::post('/media', [MediaController::class, 'store']);
        Route::get('/media', [MediaController::class, 'index']);
        
        // Campaigns
        Route::apiResource('campaigns', CampaignController::class)
             ->only(['index', 'store', 'show']);
             
        // Tools
        Route::get('/public/screens', [ScreenController::class, 'index']); 
    });
});