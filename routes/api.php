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
use App\Http\Controllers\Api\AdminCampaignController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Auth User (Rate Limited: Login Strict)
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);

// Midtrans Callback (No Auth, tapi dilindungi Signature Validation di Service)
Route::post('/callback/midtrans', [CallbackController::class, 'handleMidtrans']);

// === PLAYER / IOT ROUTES ===
// Dilindungi Rate Limiter 'player' (60rpm)
Route::prefix('player')->middleware(['throttle:player'])->group(function () {
    Route::get('/playlist', [PlayerController::class, 'getPlaylist']);
    Route::post('/telemetry', [PlayerController::class, 'telemetry']);
    Route::post('/logs/impression', [PlayerController::class, 'storeImpression']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Auth: Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Global User Info
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [DashboardController::class, 'updateProfile']);

    // === ROLE: SUPER ADMIN ===
    Route::middleware('role:super_admin')->group(function () {
        // Inventory
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
        
        // Moderasi
        Route::get('/admin/media', [AdminMediaController::class, 'index']);
        Route::put('/admin/media/{id}/approve', [AdminMediaController::class, 'approve']);
        Route::put('/admin/media/{id}/reject', [AdminMediaController::class, 'reject']);
        
        // Approval Campaign
        Route::get('/admin/campaigns', [AdminCampaignController::class, 'index']);
        Route::put('/admin/campaigns/{id}/approve', [AdminCampaignController::class, 'approve']);
        Route::put('/admin/campaigns/{id}/reject', [AdminCampaignController::class, 'reject']);

        // Analytics
        Route::get('/admin/reports/occupancy', [AdminReportController::class, 'occupancy']);
        Route::get('/admin/reports/revenue', [AdminReportController::class, 'revenue']);
    });

    // === ROLE: ADVERTISER ===
    Route::middleware('role:advertiser')->group(function () {
        // Dashboard & Finance
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::post('/deposits', [DepositController::class, 'store']);
        Route::get('/deposits', [DepositController::class, 'index']);
        
        // Assets & Campaign
        Route::post('/media', [MediaController::class, 'store']);
        Route::get('/media', [MediaController::class, 'index']);
        Route::apiResource('campaigns', CampaignController::class)->only(['index', 'store', 'show']);
        Route::get('/public/screens', [ScreenController::class, 'index']); 
        Route::get('/reports/campaign/{id}', [ReportController::class, 'show']);
    });
});