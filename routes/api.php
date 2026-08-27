<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SellerProfileController;
use App\Http\Controllers\Api\Admin\SellerController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

});

Route::middleware(['auth:sanctum', 'role:seller'])
    ->prefix('seller')
    ->group(function () {

        Route::post('/profile', [SellerProfileController::class, 'store']);
        Route::get('/profile', [SellerProfileController::class, 'show']);

    });

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/sellers', [SellerController::class, 'index']);
        Route::patch('/sellers/{seller}/approve', [SellerController::class, 'approve']);

    });