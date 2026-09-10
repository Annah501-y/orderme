<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\AdminSellerRequestController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    // Public authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Authenticated authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});


/*
|--------------------------------------------------------------------------
| Public Category Routes
|--------------------------------------------------------------------------
*/

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Public Product Routes
|--------------------------------------------------------------------------
*/

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Category Management
    |--------------------------------------------------------------------------
    */
    Route::post('/seller/profile', [SellerProfileController::class, 'store'])
    ->name('seller.profile.store');

Route::get('/seller/profile', [SellerProfileController::class, 'show'])
    ->name('seller.profile.show');
    Route::get('/admin/seller-requests', [AdminSellerRequestController::class, 'index'])
    ->middleware('permission:sellers.view')
    ->name('admin.seller-requests.index');

Route::get('/admin/seller-requests/{sellerProfile}', [AdminSellerRequestController::class, 'show'])
    ->middleware('permission:sellers.view')
    ->name('admin.seller-requests.show');

Route::patch('/admin/seller-requests/{sellerProfile}/approve', [AdminSellerRequestController::class, 'approve'])
    ->middleware('permission:sellers.approve')
    ->name('admin.seller-requests.approve');

Route::patch('/admin/seller-requests/{sellerProfile}/reject', [AdminSellerRequestController::class, 'reject'])
    ->middleware('permission:sellers.reject')
    ->name('admin.seller-requests.reject');

    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('permission:categories.create');

    Route::put('/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('permission:categories.update');

    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('permission:categories.delete');


    /*
    |--------------------------------------------------------------------------
    | Product Management
    |--------------------------------------------------------------------------
    */

    // Create product
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.create');

    // Update product
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update');

    // Delete product
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete');

    // Activate/deactivate product
    Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus'])
        ->middleware('permission:products.activate');


    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    Route::get('/cart', [CartController::class, 'show']);

    Route::post('/cart/items', [CartController::class, 'addItem']);

    Route::put('/cart/items/{item}', [CartController::class, 'updateItem']);

    Route::delete('/cart/items/{item}', [CartController::class, 'deleteItem']);

    Route::delete('/cart', [CartController::class, 'clear']);


    /*
    |--------------------------------------------------------------------------
    | Customer Orders
    |--------------------------------------------------------------------------
    */

    // Create order from cart
    Route::post('/orders', [OrderController::class, 'store']);

    // Customer's own orders
    Route::get('/orders', [OrderController::class, 'index']);

    // Customer's own order details
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // Customer cancels own order
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/payment', [OrderController::class, 'payment'])
    ->name('orders.payment');   


    /*
    |--------------------------------------------------------------------------
    | Seller Orders
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/orders', [OrderController::class, 'sellerOrders'])
        ->middleware('permission:orders.view');

    Route::get('/seller/orders/{order}', [OrderController::class, 'sellerOrder'])
        ->middleware('permission:orders.view');


    /*
    |--------------------------------------------------------------------------
    | Admin Orders
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/orders', [OrderController::class, 'adminOrders'])
        ->middleware('permission:orders.view');

    Route::get('/admin/orders/{order}', [OrderController::class, 'adminOrder'])
        ->middleware('permission:orders.view');

    Route::put('/admin/orders/{order}/status', [OrderController::class, 'adminUpdateStatus'])
        ->middleware('permission:orders.update');
     // Payments
    Route::post('/orders/{order}/payment', [OrderController::class, 'payment'])
     ->name('orders.payment'); 
     
     
    //  Buyer Wishlist
    Route::get('/wishlist',[WishlistController::class,'index']);
    Route::post('/wishlist/{product}',[WishlistController::class,'store']);
    Route::delete('/wishlist/{product}',[WishlistController::class,'destroy']);
    Route::get('/wishlist/{product}/check',[WishlistController::class,'check']);
    //Addresses


Route::get('/addresses', [AddressController::class, 'index']);
Route::post('/addresses', [AddressController::class, 'store']);
Route::get('/addresses/{address}', [AddressController::class, 'show']);
Route::put('/addresses/{address}', [AddressController::class, 'update']);
Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault']);

//profile


Route::get('/profile', [ProfileController::class, 'show']);
Route::put('/profile', [ProfileController::class, 'update']);
});
