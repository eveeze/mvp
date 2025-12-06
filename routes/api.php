<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import Controllers (Pastikan semua ada)
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
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ReportController;       // [NEW]
use App\Http\Controllers\Api\AdminReportController;  // [NEW]

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/callback/midtrans', [CallbackController::class, 'handleMidtrans']);

// Player Routes
Route::prefix('player')->group(function () {
    Route::get('/playlist', [PlayerController::class, 'getPlaylist']);
    Route::post('/telemetry', [PlayerController::class, 'telemetry']);
    Route::post('/logs/impression', [PlayerController::class, 'storeImpression']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [DashboardController::class, 'updateProfile']);

    // === SUPER ADMIN ===
    Route::middleware('role:super_admin')->group(function () {
        // Inventory
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('hotels.screens', ScreenController::class);
        
        // Moderasi
        Route::get('/admin/media', [AdminMediaController::class, 'index']);
        Route::put('/admin/media/{id}/approve', [AdminMediaController::class, 'approve']);
        Route::put('/admin/media/{id}/reject', [AdminMediaController::class, 'reject']);
        Route::get('/admin/campaigns', [AdminCampaignController::class, 'index']);
        Route::put('/admin/campaigns/{id}/approve', [AdminCampaignController::class, 'approve']);
        Route::put('/admin/campaigns/{id}/reject', [AdminCampaignController::class, 'reject']);

        // [NEW] Reports (Analytic Admin)
        Route::get('/admin/reports/occupancy', [AdminReportController::class, 'occupancy']);
        Route::get('/admin/reports/revenue', [AdminReportController::class, 'revenue']);
    });

    // === ADVERTISER ===
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

        // [NEW] Reports (Campaign Performance)
        Route::get('/reports/campaign/{id}', [ReportController::class, 'show']);
    });
});