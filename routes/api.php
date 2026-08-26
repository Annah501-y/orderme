<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SellerProfileController;
use App\Http\Controllers\Api\Admin\SellerController;

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
Route::middleware(['auth:sanctum', 'role:seller'])
->prefix('seller')
->group(function () {
    Route::post('/profile', [SellerProfileController::class, 'store']);
    Route::get('/profile', [SellerProfileController::class, 'show']);

    Route::get('/approved-test',function (Request $request) {
        return response()->json([
            'success'=> true,
            'message'=> 'Approved seller access granted.',
            'user'=> $request->user(),
        ]);
    })->middleware('approved.seller');
});

Route::middleware(['auth:sanctum','role:admin'])
->prefix('admin')
->group(function () {
    Route::get('/sellers',[SellerController::class,'index']);
    Route::patch('/sellers/{seller}/approve',[SellerController::class,'approve']);
});