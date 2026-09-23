<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSellerRequestController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminDeliveryController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminRiderController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClickPesaWebhookController;
use App\Http\Controllers\Api\DeliveryAssignmentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RiderDeliveryController;
use App\Http\Controllers\Api\RiderDeliveryOtpController;
use App\Http\Controllers\Api\RiderDeliveryStopController;
use App\Http\Controllers\Api\RiderLocationController;
use App\Http\Controllers\Api\RiderOrderController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\SellerProfileController;
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
Route::get('/deals', [ProductController::class, 'deals']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {

        Route::get(
            '/admin/dashboard',
            [AdminDashboardController::class, 'index']
        );
        Route::get(
            '/admin/users',
            [AdminUserController::class, 'index']);

        Route::put(
            '/admin/users/{user}',
            [AdminUserController::class, 'update']
        );

        Route::put(
            '/admin/users/{user}/status',
            [AdminUserController::class, 'updateStatus']
        );

        Route::get(
            '/admin/products',
            [AdminProductController::class, 'index']
        );

        Route::get(
            '/admin/products/{product}',
            [AdminProductController::class, 'show']
        );

        Route::put(
            '/admin/products/{product}/status',
            [AdminProductController::class, 'updateStatus']
        );
        Route::post('/admin/riders', [AdminRiderController::class, 'store'])
            ->middleware('permission:riders.create')
            ->name('admin.riders.store');

        Route::get('/admin/deliveries', [AdminDeliveryController::class, 'index'])
            ->middleware('permission:deliveries.assign')
            ->name('admin.deliveries.index');
        Route::post('/admin/deliveries', [AdminDeliveryController::class, 'store'])
            ->middleware('permission:deliveries.assign')
            ->name('admin.deliveries.store');

        Route::get('/admin/settings', [AdminSettingsController::class, 'show'])
            ->middleware('permission:users.view')
            ->name('admin.settings.show');

        Route::put('/admin/settings/profile', [AdminSettingsController::class, 'updateProfile'])
            ->middleware('permission:users.update')
            ->name('admin.settings.profile');

        Route::post('/admin/settings/profile-photo', [AdminSettingsController::class, 'updateProfilePhoto'])
            ->middleware('permission:users.update')
            ->name('admin.settings.profile-photo');
        Route::put('/admin/settings/password', [AdminSettingsController::class, 'updatePassword'])
            ->middleware('permission:users.update')
            ->name('admin.settings.password');

        Route::post('/admin/settings/logout-all', [AdminSettingsController::class, 'logoutAll'])
            ->middleware('permission:users.update')
            ->name('admin.settings.logout-all');

    });

    /*
    |--------------------------------------------------------------------------
    | Seller Profile
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/seller/profile',
        [SellerProfileController::class, 'store']
    )->name('seller.profile.store');

    Route::get(
        '/seller/profile',
        [SellerProfileController::class, 'show']
    )->name('seller.profile.show');

    /*
    |--------------------------------------------------------------------------
    | Admin Seller Requests
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/admin/seller-requests',
        [AdminSellerRequestController::class, 'index']
    )
        ->middleware('permission:sellers.view')
        ->name('admin.seller-requests.index');

    Route::get(
        '/admin/seller-requests/{sellerProfile}',
        [AdminSellerRequestController::class, 'show']
    )
        ->middleware('permission:sellers.view')
        ->name('admin.seller-requests.show');

    Route::patch(
        '/admin/seller-requests/{sellerProfile}/approve',
        [AdminSellerRequestController::class, 'approve']
    )
        ->middleware('permission:sellers.approve')
        ->name('admin.seller-requests.approve');

    Route::patch(
        '/admin/seller-requests/{sellerProfile}/reject',
        [AdminSellerRequestController::class, 'reject']
    )
        ->middleware('permission:sellers.reject')
        ->name('admin.seller-requests.reject');

    /*
    |--------------------------------------------------------------------------
    | Category Management
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/categories',
        [CategoryController::class, 'adminIndex']);

    Route::post(
        '/categories',
        [CategoryController::class, 'store']
    )->middleware('permission:categories.create');

    Route::put(
        '/categories/{category}',
        [CategoryController::class, 'update']
    )->middleware('permission:categories.update');

    Route::delete(
        '/categories/{category}',
        [CategoryController::class, 'destroy']
    )->middleware('permission:categories.delete');

    /*
    |--------------------------------------------------------------------------
    | Product Management
    |--------------------------------------------------------------------------
    */

    // Seller's own products
    Route::get(
        '/seller/products',
        [ProductController::class, 'sellerProducts']
    )->middleware('permission:products.view');

    // Create product
    Route::post(
        '/products',
        [ProductController::class, 'store']
    )->middleware('permission:products.create');

    // Update product
    Route::put(
        '/products/{product}',
        [ProductController::class, 'update']
    )->middleware('permission:products.update');

    // Delete product
    Route::delete(
        '/products/{product}',
        [ProductController::class, 'destroy']
    )->middleware('permission:products.delete');

    // Activate / deactivate product
    Route::patch(
        '/products/{product}/status',
        [ProductController::class, 'updateStatus']
    )->middleware('permission:products.activate');

    // Manage product stock
    Route::patch(
        '/products/{product}/stock',
        [ProductController::class, 'updateStock']
    )->middleware('permission:products.manage_stock');

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/cart',
        [CartController::class, 'show']
    );

    Route::post(
        '/cart/items',
        [CartController::class, 'addItem']
    );

    Route::put(
        '/cart/items/{item}',
        [CartController::class, 'updateItem']
    );

    Route::delete(
        '/cart/items/{item}',
        [CartController::class, 'deleteItem']
    );

    Route::delete(
        '/cart',
        [CartController::class, 'clear']
    );

    /*
    |--------------------------------------------------------------------------
    | Customer Orders
    |--------------------------------------------------------------------------
    */

    // Create order from cart
    // Route::POST(
    //     '/orders',
    //     [OrderController::class, 'checkout']
    // );

    Route::post(
        '/orders/checkout',
        [OrderController::class, 'checkout']
    );
    Route::post('/orders/calculate-delivery',
        [OrderController::class, 'calculateDeliveryFee']);

    // Customer's own orders
    Route::get(
        '/orders',
        [OrderController::class, 'index']
    );

    // Customer's own order details
    Route::get(
        '/orders/{order}',
        [OrderController::class, 'show']
    );

    // Customer cancels own order
    Route::put(
        '/orders/{order}/cancel',
        [OrderController::class, 'cancel']
    );

    // Customer payment
    Route::post(
        '/orders/{order}/payment',
        [OrderController::class, 'payment']
    )->name('orders.payment');

    /*
    |--------------------------------------------------------------------------
    | Seller Orders
    |--------------------------------------------------------------------------
    */

    // Seller's orders
    Route::get(
        '/seller/orders',
        [OrderController::class, 'sellerOrders']
    )->middleware('permission:orders.view');

    // Seller order details
    Route::get(
        '/seller/orders/{sellerOrder}',
        [OrderController::class, 'sellerOrder']
    )->middleware('permission:orders.view');

    // Seller updates own seller-order status
    Route::put(
        '/seller/orders/{sellerOrder}/status',
        [OrderController::class, 'sellerUpdateStatus']
    )->middleware('permission:orders.update');

    /*
    |--------------------------------------------------------------------------
    | Admin Orders
    |--------------------------------------------------------------------------
    */

    // Admin order list
    Route::get(
        '/admin/orders',
        [OrderController::class, 'adminOrders']
    )->middleware('permission:orders.view');

    // Admin order details
    Route::get(
        '/admin/orders/{order}',
        [OrderController::class, 'adminOrder']
    )->middleware('permission:orders.view');

    // Admin updates main order status
    Route::put(
        '/admin/orders/{order}/status',
        [OrderController::class, 'adminUpdateStatus']
    )->middleware('permission:orders.update');

    /*
    |--------------------------------------------------------------------------
    | Wishlist
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/wishlist',
        [WishlistController::class, 'index']
    );

    Route::post(
        '/wishlist/{product}',
        [WishlistController::class, 'store']
    );

    Route::delete(
        '/wishlist/{product}',
        [WishlistController::class, 'destroy']
    );

    Route::get(
        '/wishlist/{product}/check',
        [WishlistController::class, 'check']
    );

    /*
    |--------------------------------------------------------------------------
    | Addresses
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/addresses',
        [AddressController::class, 'index']
    );

    Route::post(
        '/addresses',
        [AddressController::class, 'store']
    );

    Route::get(
        '/addresses/{address}',
        [AddressController::class, 'show']
    );

    Route::put(
        '/addresses/{address}',
        [AddressController::class, 'update']
    );

    Route::delete(
        '/addresses/{address}',
        [AddressController::class, 'destroy']
    );

    Route::patch(
        '/addresses/{address}/default',
        [AddressController::class, 'setDefault']
    );

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile',
        [ProfileController::class, 'show']
    );

    Route::put(
        '/profile',
        [ProfileController::class, 'update']
    );

    Route::get('/rider/orders', [RiderOrderController::class, 'index'])
        ->middleware('permission:deliveries.view')
        ->name('riders.orders.index');

    Route::put('/rider/location', [RiderLocationController::class, 'update'])
        ->middleware('permission:deliveries.update')
        ->name('rider.location.update');

    Route::post(
        '/orders/{orderId}/assign-delivery',
        [DeliveryAssignmentController::class, 'assign']
    )->name('orders.assign-delivery');

    Route::get(
        '/rider/deliveries',
        [RiderDeliveryController::class, 'index']
    )->middleware('permission:deliveries.view')
        ->name('rider.deliveries.index');
    Route::put('/rider/deliveries/{delivery}', [RiderDeliveryController::class, 'update'])
        ->middleware('permission:deliveries.update')
        ->name('rider.deliveries.update');

    Route::put('/rider/delivery-stops/{stop}', [RiderDeliveryStopController::class, 'update'])
        ->middleware('permission:deliveries.update')
        ->name('rider.delivery-stops.update');
    Route::post('/rider/deliveries/{delivery}/otp', [RiderDeliveryOtpController::class, 'generate'])
        ->middleware('permission:deliveries.update')
        ->name('rider.deliveries.otp.generate');

    Route::post('/rider/deliveries/{delivery}/otp/verify', [RiderDeliveryOtpController::class, 'verify'])
        ->middleware('permission:deliveries.update')
        ->name('rider.deliveries.otp.verify');

});

Route::post('/webhooks/clickpesa', [ClickPesaWebhookController::class, 'handle']);

Route::post('/rider/activate', [AdminRiderController::class, 'activate'])
    ->name('rider.activate');
