<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
Route::middleware('auth:sanctum')->prefix('test')->group(function () {

    Route::get('/buyer', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Buyer access granted.',
            'user' => $request->user(),
        ]);
    })->middleware('role:buyer');

    Route::get('/seller', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Seller access granted.',
            'user' => $request->user(),
        ]);
    })->middleware('role:seller');

    Route::get('/admin', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Admin access granted.',
            'user' => $request->user(),
        ]);
    })->middleware('role:admin');

});