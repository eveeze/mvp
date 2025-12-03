<?php
/** @var \Illuminate\Routing\Router $router */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\ScreenController;

Route::prefix('auth')->group(function () {
    Route::post('register-advertiser', [AuthController::class, 'registerAdvertiser']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

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
    });
});
