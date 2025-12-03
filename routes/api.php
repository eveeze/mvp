<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\ScreenController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\CallbackController; // Controller baru untuk Midtrans
use App\Http\Controllers\Api\MediaController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// === Public Routes ===

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register-advertiser', [AuthController::class, 'registerAdvertiser']);
    Route::post('login', [AuthController::class, 'login']);
});

// MIDTRANS CALLBACK / WEBHOOK
// Penting: Route ini harus PUBLIC (di luar middleware auth:sanctum)
// Midtrans akan mengirim POST request ke sini untuk update status pembayaran
Route::post('callback/midtrans', [CallbackController::class, 'handleMidtrans']);


// === Protected Routes (Butuh Token) ===
Route::middleware('auth:sanctum')->group(function () {
    
    // Global User Info
    Route::get('/me', [AuthController::class, 'me']);

    // === Role: Superadmin ===
    Route::middleware('role:superadmin')->group(function () {
        // CMS Hotel
        Route::apiResource('hotels', HotelController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        // CMS Screen per Hotel (nested)
        Route::prefix('hotels/{hotel}')->group(function () {
            Route::get('screens', [ScreenController::class, 'index']);
            Route::post('screens', [ScreenController::class, 'store']);
            Route::get('screens/{screen}', [ScreenController::class, 'show']);
            Route::put('screens/{screen}', [ScreenController::class, 'update']);
            Route::delete('screens/{screen}', [ScreenController::class, 'destroy']);
        });

        // Deposit Approval
        // Jika menggunakan Midtrans full otomatis, ini mungkin jarang dipakai.
        // Tapi tetap bisa disimpan untuk admin mengecek/mengelola deposit manual jika ada.
        Route::post('/admin/deposits/{id}/approve', [DepositController::class, 'approve']);
    });

    // === Role: Advertiser ===
    Route::middleware('role:advertiser')->group(function () {
        // Wallet & Deposit
        // GET: Melihat histori deposit
        Route::get('/my-deposits', [DepositController::class, 'index']); 
        
        // POST: Request Deposit Baru (Generate Snap Token Midtrans)
        Route::post('/deposits', [DepositController::class, 'store']);  
        
        Route::post('/media', [MediaController::class, 'store']); // Upload
        Route::get('/media', [MediaController::class, 'index']);  // List & Check Status
    });

});