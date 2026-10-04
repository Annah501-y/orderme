<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSellerRequestController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminDeliveryController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminRiderController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClickPesaWebhookController;
use App\Http\Controllers\Api\CustomerFeedbackController;
use App\Http\Controllers\Api\DeliveryAssignmentController;
use App\Http\Controllers\Api\EarningsController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\GuestCheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PayoutDestinationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RiderApplicationController;
use App\Http\Controllers\Api\RiderDeliveryController;
use App\Http\Controllers\Api\RiderDeliveryOtpController;
use App\Http\Controllers\Api\RiderDeliveryStopController;
use App\Http\Controllers\Api\RiderLocationController;
use App\Http\Controllers\Api\RiderOrderController;
use App\Http\Controllers\Api\RiderProfileController;
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

    Route::post('/google/exchange', [GoogleAuthController::class, 'exchange'])
        ->middleware('throttle:10,1');

    Route::post('/login', [AuthController::class, 'login']);

    // Authenticated authentication
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::post('/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::post('/rider/applications', [RiderApplicationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('rider.applications.submit');

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

// Rate-limit public quote and order endpoints that use geocoding or create persistent records.
Route::post('/guest/orders/calculate-delivery', [GuestCheckoutController::class, 'calculate'])
    ->middleware('throttle:20,1');
Route::post('/guest/orders/checkout', [GuestCheckoutController::class, 'store'])
    ->middleware('throttle:10,1');
Route::post('/guest/orders/{order}/payment', [OrderController::class, 'payment'])
    ->middleware('throttle:10,1')
    ->name('guest.orders.payment');

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
        Route::get('/admin/rider-licenses', [AdminRiderController::class, 'licenseSubmissions'])
            ->middleware('permission:riders.view')
            ->name('admin.rider-licenses.index');
        Route::get('/admin/riders/{rider}/license-document', [AdminRiderController::class, 'downloadLicense'])
            ->middleware('permission:riders.view')
            ->name('admin.riders.license-document');
        Route::patch('/admin/riders/{rider}/license-review', [AdminRiderController::class, 'reviewLicense'])
            ->middleware('permission:riders.update')
            ->name('admin.riders.license-review');
        Route::get('/admin/rider-applications', [AdminRiderController::class, 'applications'])
            ->middleware('permission:riders.view')
            ->name('admin.rider-applications.index');
        Route::post('/admin/rider-applications/{riderApplication}/invite', [AdminRiderController::class, 'inviteApplicant'])
            ->middleware('permission:riders.create')
            ->name('admin.rider-applications.invite');

        Route::get('/admin/deliveries/ready', [AdminDeliveryController::class, 'readyForAssignment'])
            ->middleware('permission:deliveries.assign')
            ->name('admin.deliveries.ready');
        Route::get('/admin/deliveries', [AdminDeliveryController::class, 'index'])
            ->middleware('permission:deliveries.assign')
            ->name('admin.deliveries.index');
        Route::get('/admin/payments', [AdminPaymentController::class, 'index'])
            ->middleware('permission:payments.view')
            ->name('admin.payments.index');
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
        Route::get(
            '/admin/reviews',
            [CustomerFeedbackController::class, 'reviewsForModeration']
        );

        Route::patch(
            '/admin/reviews/{review}',
            [CustomerFeedbackController::class, 'updateReviewStatus']
        );

        Route::get(
            '/admin/feedback/comments',
            [CustomerFeedbackController::class, 'commentsForModeration']
        );

        Route::patch(
            '/admin/feedback/comments/{feedbackComment}',
            [CustomerFeedbackController::class, 'updateCommentStatus']
        );

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

    Route::get(
        '/admin/seller-requests/{sellerProfile}/business-license',
        [AdminSellerRequestController::class, 'downloadBusinessLicense']
    )
        ->middleware('permission:sellers.view')
        ->name('admin.seller-requests.business-license');

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
    )->middleware(['permission:products.view', 'seller.approved']);

    // Create product
    Route::post(
        '/products',
        [ProductController::class, 'store']
    )->middleware(['permission:products.create', 'seller.approved']);

    // Update product
    Route::put(
        '/products/{product}',
        [ProductController::class, 'update']
    )->middleware(['permission:products.update', 'seller.approved']);

    // Delete product
    Route::delete(
        '/products/{product}',
        [ProductController::class, 'destroy']
    )->middleware(['permission:products.delete', 'seller.approved']);

    // Activate / deactivate product
    Route::patch(
        '/products/{product}/status',
        [ProductController::class, 'updateStatus']
    )->middleware(['permission:products.activate', 'seller.approved']);

    // Manage product stock
    Route::patch(
        '/products/{product}/stock',
        [ProductController::class, 'updateStock']
    )->middleware(['permission:products.manage-stock', 'seller.approved']);

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

    Route::post('/guest/orders/{order}/claim', [GuestCheckoutController::class, 'claim'])
        ->middleware('throttle:10,1')
        ->name('guest.orders.claim');

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
    )->middleware(['permission:orders.view', 'seller.approved']);

    // Seller order details
    Route::get(
        '/seller/orders/{sellerOrder}',
        [OrderController::class, 'sellerOrder']
    )->middleware(['permission:orders.view', 'seller.approved']);

    // Seller updates own seller-order status
    Route::put(
        '/seller/orders/{sellerOrder}/status',
        [OrderController::class, 'sellerUpdateStatus']
    )->middleware(['permission:orders.update', 'seller.approved']);

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

    Route::get('/earnings', [EarningsController::class, 'index'])
        ->middleware('role:seller|rider')
        ->name('earnings.index');

    // Rider licence documents are served through authenticated routes, never public storage URLs.
    Route::get('/rider/profile', [RiderProfileController::class, 'show'])
        ->name('rider.profile.show');
    Route::put('/rider/profile', [RiderProfileController::class, 'update'])
        ->middleware('permission:deliveries.update')
        ->name('rider.profile.update');

    Route::put('/rider/location', [RiderLocationController::class, 'update'])
        ->middleware('permission:deliveries.update')
        ->name('rider.location.update');

    Route::post(
        '/orders/{orderId}/assign-delivery',
        [DeliveryAssignmentController::class, 'assign']
    )->middleware('permission:deliveries.assign')
        ->name('orders.assign-delivery');

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
        ->middleware('throttle:3,1')
        ->name('rider.deliveries.otp.generate');

    Route::post('/rider/deliveries/{delivery}/otp/verify', [RiderDeliveryOtpController::class, 'verify'])
        ->middleware('permission:deliveries.update')
        ->middleware('throttle:6,1')
        ->name('rider.deliveries.otp.verify');

    Route::put(
        '/payout-destination', [PayoutDestinationController::class, 'store']
    )->name('payout-destination.update');
    Route::get('/payout-destination', [PayoutDestinationController::class, 'show'])
        ->name('payout-destination.show');

    Route::post(
        '/payout-destination/verification/send', [PayoutDestinationController::class, 'sendVerification']
    )->middleware('throttle:3,1')->name('payout-destination.verification.send');

    Route::post(
        '/payout-destination/verification/verify', [PayoutDestinationController::class, 'verify']
    )->middleware('throttle:6,1')->name('payout-destination.verification.verify');

    Route::post(
        '/reviews',
        [CustomerFeedbackController::class, 'storeReview']
    );

    Route::post(
        '/feedback/comments',
        [CustomerFeedbackController::class, 'storeComment']
    );

});

Route::post('/webhooks/clickpesa', [ClickPesaWebhookController::class, 'handle']);

Route::post('/rider/activate', [AdminRiderController::class, 'activate'])
    ->name('rider.activate');
Route::get(
    '/products/{product}/reviews',
    [CustomerFeedbackController::class, 'approvedReviews']
);

Route::get(
    '/faqs/{faqId}/comments',
    [CustomerFeedbackController::class, 'approvedFaqComments']
);
