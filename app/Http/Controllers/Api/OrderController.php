<?php

namespace App\Http\Controllers\Api;
use App\Exceptions\ClickPesaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Requests\CheckoutOrderRequest;
use App\Http\Requests\DeliveryCalculationRequest;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DeliveryFeeService;
use App\Services\ClickPesaService;
use App\Services\RoutingService;
use Throwable;

class OrderController extends Controller
{
    /**
     * Get customer's orders.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with([
                'items.product',
                'sellerOrders.seller',
            ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get a single customer's order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $order->load([
            'items.product.seller',
            'sellerOrders.seller',
        ]);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Checkout cart and create order.
     */
   /**
 * Checkout selected cart items and create order.
 *
 * The frontend sends the cart item IDs selected by
 * the customer. The backend validates ownership,
 * stock, prices, and seller information.
 */

 public function checkout(
    CheckoutOrderRequest $request,
    RoutingService $routingService,
    DeliveryFeeService $deliveryFeeService
): JsonResponse {
    $user = $request->user();

    $cartItemIds = $request->input('cart_item_ids');
    $addressId = $request->input('address_id');
    $vehicleType = $request->input('vehicle_type');

    /*
    |--------------------------------------------------------------------------
    | 1. Validate customer's delivery address
    |--------------------------------------------------------------------------
    */

    $customerAddress = Address::where('id', $addressId)
        ->where('user_id', $user->id)
        ->first();

    if (! $customerAddress) {
        return response()->json([
            'success' => false,
            'message' => 'The selected delivery address is invalid.',
        ], 422);
    }

    if (
        $customerAddress->latitude === null ||
        $customerAddress->longitude === null
    ) {
        return response()->json([
            'success' => false,
            'message' =>
                'The selected address does not have valid coordinates.',
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Load selected cart items
    |--------------------------------------------------------------------------
    */

    $cart = Cart::where('user_id', $user->id)
        ->with([
            'items.product.seller',
            'items.product.category',
        ])
        ->first();

    if (! $cart) {
        return response()->json([
            'success' => false,
            'message' => 'Your cart is empty.',
        ], 422);
    }

    $cartItems = $cart->items
        ->whereIn('id', $cartItemIds)
        ->values();

    if (
        $cartItems->isEmpty() ||
        $cartItems->count() !== count($cartItemIds)
    ) {
        return response()->json([
            'success' => false,
            'message' =>
                'One or more selected cart items are invalid.',
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Validate products and calculate subtotal
    |--------------------------------------------------------------------------
    */

    $subtotal = 0;

    foreach ($cartItems as $cartItem) {
        $product = $cartItem->product;

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' =>
                    'One of the selected products no longer exists.',
            ], 422);
        }

        if (! $product->is_active) {
            return response()->json([
                'success' => false,
                'message' =>
                    "The product {$product->name} is no longer available.",
            ], 422);
        }

        if ($cartItem->quantity > $product->stock_quantity) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Insufficient stock for {$product->name}.",
            ], 422);
        }

        $subtotal +=
            $cartItem->quantity * $product->price;
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Group selected items by seller
    |--------------------------------------------------------------------------
    */

    $sellerGroups = $cartItems->groupBy(
        fn ($cartItem) => $cartItem->product->seller_id
    );

    $sellerDeliveryData = [];
    $totalDeliveryFee = 0;

    /*
    |--------------------------------------------------------------------------
    | 5. Calculate real route and delivery fee for each seller
    |--------------------------------------------------------------------------
    */

    foreach ($sellerGroups as $sellerId => $sellerItems) {

        $sellerAddress = Address::where('user_id', $sellerId)
            ->orderByDesc('is_default')
            ->latest()
            ->first();

        if (! $sellerAddress) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Seller {$sellerId} has not added a delivery address.",
            ], 422);
        }

        if (
            $sellerAddress->latitude === null ||
            $sellerAddress->longitude === null
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Seller {$sellerId} does not have valid address coordinates.",
            ], 422);
        }

        try {
            $route = $routingService->getDrivingRoute(
                (float) $sellerAddress->latitude,
                (float) $sellerAddress->longitude,
                (float) $customerAddress->latitude,
                (float) $customerAddress->longitude
            );

            $delivery = $deliveryFeeService->calculate(
                $route['distance_km'],
                $vehicleType
            );
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to calculate delivery for one of the sellers.',
            ], 422);
        }

        $sellerSubtotal = $sellerItems->sum(
            fn ($item) =>
                $item->quantity * $item->product->price
        );

        $sellerDeliveryData[$sellerId] = [
            'seller_subtotal' => $sellerSubtotal,
            'distance_km' => $route['distance_km'],
            'duration_minutes' => $route['duration_minutes'],
            'delivery_fee' => $delivery['delivery_fee'],
        ];

        $totalDeliveryFee += $delivery['delivery_fee'];
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Calculate final total
    |--------------------------------------------------------------------------
    */

    $totalAmount = $subtotal + $totalDeliveryFee;

    /*
    |--------------------------------------------------------------------------
    | 7. Create order and seller orders in one transaction
    |--------------------------------------------------------------------------
    */

    $order = DB::transaction(function () use (
        $user,
        $addressId,
        $vehicleType,
        $subtotal,
        $totalDeliveryFee,
        $totalAmount,
        $cart,
        $cartItems,
        $sellerDeliveryData
    ) {

        $order = Order::create([
            'user_id' => $user->id,
            'address_id' => $addressId,
            'status' => 'pending',
            'subtotal' => $subtotal,
            'delivery_fee' => $totalDeliveryFee,
            'vehicle_type' => $vehicleType,
            'total_amount' => $totalAmount,
        ]);

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            $itemTotal =
                $cartItem->quantity * $product->price;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $product->price,
                'total_price' => $itemTotal,
            ]);

            $product->decrement(
                'stock_quantity',
                $cartItem->quantity
            );
        }

        foreach ($sellerDeliveryData as $sellerId => $deliveryData) {
            SellerOrder::create([
                'order_id' => $order->id,
                'seller_id' => $sellerId,
                'status' => 'pending',
                'seller_total' =>
                    $deliveryData['seller_subtotal'],
                'distance_km' =>
                    $deliveryData['distance_km'],
                'duration_minutes' =>
                    $deliveryData['duration_minutes'],
                'delivery_fee' =>
                    $deliveryData['delivery_fee'],
            ]);
        }

        $cart->items()
            ->whereIn('id', $cartItems->pluck('id'))
            ->delete();

        return $order;
    });

    $order->load([
        'address',
        'items.product.seller',
        'sellerOrders.seller',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Order created successfully.',
        'data' => $order,
    ], 201);
}

public function calculateDeliveryFee(
    DeliveryCalculationRequest $request,
    RoutingService $routingService,
    DeliveryFeeService $deliveryFeeService
): JsonResponse {
    $user = $request->user();

    $validated = $request->validated();

    $addressId = $validated['address_id'];
    $vehicleType = $validated['vehicle_type'];
    $cartItemIds = $validated['cart_item_ids'];

    $customerAddress = Address::where('id', $addressId)
        ->where('user_id', $user->id)
        ->first();

    if (! $customerAddress) {
        return response()->json([
            'message' =>
                'The selected address does not belong to you.',
        ], 403);
    }

    if (
        $customerAddress->latitude === null ||
        $customerAddress->longitude === null
    ) {
        return response()->json([
            'message' =>
                'Your selected address does not have valid location coordinates.',
        ], 422);
    }

    $cart = Cart::where('user_id', $user->id)
        ->with([
            'items.product.seller.addresses',
        ])
        ->first();

    if (! $cart) {
        return response()->json([
            'message' => 'Your cart is empty.',
        ], 422);
    }

    $selectedItems = $cart->items
        ->whereIn('id', $cartItemIds)
        ->values();

    if ($selectedItems->isEmpty()) {
        return response()->json([
            'message' =>
                'The selected cart items could not be found.',
        ], 422);
    }

    $subtotal = 0;

    foreach ($selectedItems as $cartItem) {
        if (! $cartItem->product) {
            return response()->json([
                'message' =>
                    'One of the selected products is no longer available.',
            ], 422);
        }

        if (
            ! $cartItem->product->is_active ||
            $cartItem->product->stock_quantity <
                $cartItem->quantity
        ) {
            return response()->json([
                'message' =>
                    "Product {$cartItem->product->name} is unavailable or has insufficient stock.",
            ], 422);
        }

        $subtotal +=
            (float) $cartItem->product->price *
            (int) $cartItem->quantity;
    }

    $sellerGroups = $selectedItems->groupBy(
        fn ($cartItem) =>
            $cartItem->product->seller_id
    );

    $totalDeliveryFee = 0;
    $deliveryBreakdown = [];

    foreach ($sellerGroups as $sellerId => $sellerItems) {
        $seller = $sellerItems
            ->first()
            ->product
            ->seller;

        $sellerAddress = $seller->addresses
            ->sortByDesc('is_default')
            ->sortByDesc('created_at')
            ->first();

        if (! $sellerAddress) {
            return response()->json([
                'message' =>
                    "Seller {$seller->name} does not have a saved delivery address.",
            ], 422);
        }

        if (
            $sellerAddress->latitude === null ||
            $sellerAddress->longitude === null
        ) {
            return response()->json([
                'message' =>
                    "Seller {$seller->name} does not have valid location coordinates.",
            ], 422);
        }

        try {
            $route = $routingService->getDrivingRoute(
                (float) $sellerAddress->latitude,
                (float) $sellerAddress->longitude,
                (float) $customerAddress->latitude,
                (float) $customerAddress->longitude
            );

            $feeData = $deliveryFeeService->calculate(
                (float) $route['distance_km'],
                $vehicleType
            );
        // } catch (Throwable $exception) {
        //     report($exception);

        //     return response()->json([
        //         'message' =>
        //             'Unable to calculate delivery for one of the sellers.',
        //     ], 503);
        // }
         } catch(Throwable $exception){
            report($exception);
            return response()->json([
                    'message'=>$exception->getFile(),
                    'line'=>$exception->getLine(),
            ],500);
        }

        $sellerSubtotal = $sellerItems->sum(
            fn ($cartItem) =>
                (float) $cartItem->product->price *
                (int) $cartItem->quantity
        );

        $totalDeliveryFee +=
            (float) $feeData['delivery_fee'];

        $deliveryBreakdown[] = [
            'seller_id' => $sellerId,
            'seller_name' => $seller->name,
            'seller_subtotal' => round($sellerSubtotal, 2),
            'distance_km' => $route['distance_km'],
            'duration_minutes' =>
                $route['duration_minutes'],
            'vehicle_type' =>
                $feeData['vehicle_type'],
            'rate_per_km' =>
                $feeData['rate_per_km'],
            'delivery_fee' =>
                $feeData['delivery_fee'],
        ];
    }

    $totalAmount = $subtotal + $totalDeliveryFee;

    return response()->json([
        'message' =>
            'Delivery fee calculated successfully.',

        'data' => [
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round($totalDeliveryFee, 2),
            'total_amount' => round($totalAmount, 2),
            'vehicle_type' => $vehicleType,
            'delivery_breakdown' => $deliveryBreakdown,
        ],
    ]);
}
/**
     * Cancel customer's entire order.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $allowedStatuses = [
            'pending',
            'confirmed',
            'processing',
        ];

        if (! in_array($order->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Order cannot be cancelled while its status is {$order->status}.",
            ], 422);
        }

        DB::transaction(function () use ($order) {

            $order->load('items.product');

            /*
             * Return ordered quantities to stock.
             */
            foreach ($order->items as $item) {

                if ($item->product) {
                    $item->product->increment(
                        'stock_quantity',
                        $item->quantity
                    );
                }
            }

            /*
             * Cancel main order.
             */
            $order->update([
                'status' => 'cancelled',
            ]);

            /*
             * Cancel all seller orders belonging
             * to this customer order.
             */
            $order->sellerOrders()->update([
                'status' => 'cancelled',
            ]);
        });

        $order->load([
            'items.product.seller',
            'sellerOrders.seller',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'data' => $order,
        ]);
    }

    /**
     * Get orders belonging to the authenticated seller.
     *
     * Only seller-specific orders and items are returned.
     */
    public function sellerOrders(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;

        $sellerOrders = SellerOrder::where('seller_id', $sellerId)
            ->with([
                'order.user',

                'order.items' => function ($query) use ($sellerId) {
                    $query->whereHas('product', function ($productQuery) use ($sellerId) {
                        $productQuery->where('seller_id', $sellerId);
                    });
                },

                'order.items.product',
            ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $sellerOrders,
        ]);
    }

    /**
     * Get a single seller order.
     *
     * Seller can only access their own seller order.
     */
    public function sellerOrder(
        Request $request,
        SellerOrder $sellerOrder
    ): JsonResponse {
        $sellerId = $request->user()->id;

        if ($sellerOrder->seller_id !== $sellerId) {
            abort(404);
        }

        $sellerOrder->load([
            'seller',
            'order.user',

            'order.items' => function ($query) use ($sellerId) {
                $query->whereHas('product', function ($productQuery) use ($sellerId) {
                    $productQuery->where('seller_id', $sellerId);
                });
            },

            'order.items.product',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'seller_order_id' => $sellerOrder->id,
                'order_id' => $sellerOrder->order_id,

                'customer' => [
                    'id' => $sellerOrder->order->user->id,
                    'name' => $sellerOrder->order->user->name,
                ],

                'status' => $sellerOrder->status,
                'seller_total' => $sellerOrder->seller_total,

                'items' => $sellerOrder->order->items,

                'created_at' => $sellerOrder->created_at,
                'updated_at' => $sellerOrder->updated_at,
            ],
        ]);
    }

    /**
     * Seller updates their own seller order status.
     *
     * Workflow:
     *
     * pending
     *    ↓
     * confirmed
     *    ↓
     * processing
     *    ↓
     * ready_for_delivery
     *
     * Delivery statuses are handled separately.
     */
    public function sellerUpdateStatus(
        UpdateOrderStatusRequest $request,
        SellerOrder $sellerOrder
    ): JsonResponse {
        $sellerId = $request->user()->id;

        /*
         * SECURITY CHECK:
         * Seller can only update their own seller order.
         */
        if ($sellerOrder->seller_id !== $sellerId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this order.',
            ], 403);
        }

        $currentStatus = $sellerOrder->status;
        $newStatus = $request->status;

        /*
         * Allowed seller status transitions.
         */
        $allowedTransitions = [
            'pending' => [
                'confirmed',
                'cancelled',
            ],

            'confirmed' => [
                'processing',
                'cancelled',
            ],

            'processing' => [
                'ready_for_delivery',
                'cancelled',
            ],

            'ready_for_delivery' => [],

            'cancelled' => [],

            /*
             * Delivery statuses are not controlled by sellers.
             */
            'assigned' => [],
            'picked_up' => [],
            'out_for_delivery' => [],
            'delivered' => [],
        ];

        /*
         * Prevent invalid status transitions.
         */
        if (! in_array(
            $newStatus,
            $allowedTransitions[$currentStatus] ?? [],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Cannot change seller order status from {$currentStatus} to {$newStatus}.",
            ], 422);
        }

        /*
         * Update seller order.
         */
        $sellerOrder->update([
            'status' => $newStatus,
        ]);

        /*
         * Refresh only the seller order.
         *
         * IMPORTANT:
         * Do NOT load order.items here.
         *
         * The parent order may contain products belonging
         * to other sellers.
         */
        $sellerOrder->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Seller order status updated successfully.',
            'data' => [
                'seller_order_id' => $sellerOrder->id,
                'order_id' => $sellerOrder->order_id,
                'seller_id' => $sellerOrder->seller_id,
                'status' => $sellerOrder->status,
                'seller_total' => $sellerOrder->seller_total,
            ],
        ]);
    }

    /**
     * Get all orders for admin.
     */
   
   public function adminOrders(Request $request): JsonResponse
   {
       $query = Order::query()
           ->with([
               'user',
               'sellerOrders.seller',
               'items.product.seller',
           ])
           ->latest();
   
       /*
        * Search by order ID or customer information.
        */
       if ($request->filled('search')) {
           $search = $request->search;
   
           $query->where(function ($q) use ($search) {
   
               // Search by order ID
               if (is_numeric($search)) {
                   $q->orWhere('id', $search);
               }
   
               // Search by customer name/email
               $q->orWhereHas('user', function ($userQuery) use ($search) {
                   $userQuery
                       ->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
               });
           });
       }
   
       /*
        * Filter by order status.
        */
       if ($request->filled('status')) {
           $query->where('status', $request->status);
       }
   
       $orders = $query->paginate(20);
   
       return response()->json([
           'success' => true,
           'message' => 'Orders retrieved successfully.',
           'data' => $orders,
       ]);
   }
   
   
   /**
    * Get a single order for admin.
    */
   public function adminOrder(Order $order): JsonResponse
   {
       $order->load([
           'user',
           'sellerOrders.seller',
           'items.product.seller',
           'payments',
       ]);
   
       return response()->json([
           'success' => true,
           'message' => 'Order retrieved successfully.',
           'data' => $order,
       ]);
   }
    /**
     * Admin updates main order status.
     *
     * Workflow:
     *
     * pending
     *    ↓
     * confirmed
     *    ↓
     * processing
     *    ↓
     * ready_for_delivery
     *    ↓
     * assigned
     *    ↓
     * picked_up
     *    ↓
     * out_for_delivery
     *    ↓
     * delivered
     */
    public function adminUpdateStatus(
        UpdateOrderStatusRequest $request,
        Order $order
    ): JsonResponse {
        $currentStatus = $order->status;
        $newStatus = $request->status;

        $allowedTransitions = [
            'pending' => [
                'confirmed',
                'cancelled',
            ],

            'confirmed' => [
                'processing',
                'cancelled',
            ],

            'processing' => [
                'ready_for_delivery',
                'cancelled',
            ],

            'ready_for_delivery' => [
                'assigned',
            ],

            'assigned' => [
                'picked_up',
            ],

            'picked_up' => [
                'out_for_delivery',
            ],

            'out_for_delivery' => [
                'delivered',
            ],

            'delivered' => [],

            'cancelled' => [],
        ];

        /*
         * Prevent invalid transitions.
         */
        if (! in_array(
            $newStatus,
            $allowedTransitions[$currentStatus] ?? [],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Cannot change order status from {$currentStatus} to {$newStatus}.",
            ], 422);
        }

        /*
         * Update main order.
         */
        $order->update([
            'status' => $newStatus,
        ]);

        /*
         * Return complete order to admin.
         */
        $order = $order->fresh()->load([
            'user',
            'sellerOrders.seller',
            'items.product.seller',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => $order,
        ]);
    }

    /**
     * Create a payment for an order.
     */


    
     

   
      
     
     public function payment(
         StorePaymentRequest $request,
         Order $order,
         ClickPesaService $clickPesa
     ): JsonResponse {
         /*
          * Customer can only pay for their own order.
          */
         if ($order->user_id !== $request->user()->id) {
             abort(404);
         }
     
         /*
          * Prevent duplicate active payments.
          */
         $existingPayment = $order->payments()
             ->whereIn('status', [
                 'pending',
                 'processing',
                 'paid',
             ])
             ->latest()
             ->first();
     
         if ($existingPayment) {
             return response()->json([
                 'success' => false,
                 'message' => 'A payment already exists for this order.',
                 'data' => [
                     'payment' => $existingPayment,
                 ],
             ], 422);
         }
     
         /*
          * Payment is only allowed for pending/confirmed orders.
          */
         if (! in_array(
             $order->status,
             [
                 'pending',
                 'confirmed',
             ],
             true
         )) {
             return response()->json([
                 'success' => false,
                 'message' =>
                     "Payment cannot be made while the order status is {$order->status}.",
             ], 422);
         }
     
         /*
          * Create payment record.
          */
         $payment = Payment::create([
             'order_id' => $order->id,
             'user_id' => $request->user()->id,
             'provider' => 'clickpesa',
             'method' => $request->input('method'),
             'amount' => $order->total_amount,
             'currency' => 'TZS',
             'status' => 'pending',
         ]);
     
         /*
          * Actually trigger the ClickPesa USSD push / PIN prompt on the
          * customer's phone. This is the step that was previously missing.
          */
         // ClickPesa order references must be alphanumeric only, max 20 chars.
         // Combine order + payment id to keep each attempt unique even on retry.
         $orderReference = "o{$order->id}p{$payment->id}";
     
         try {
             $result = $clickPesa->initiateUssdPush([
                 'amount' => $order->total_amount,
                 'currency' => 'TZS',
                 'order_reference' => $orderReference,
                 'phone_number' => $request->input('phone_number'), // adjust to however the customer's number reaches this request
             ]);
     
             $payment->update([
                 'status' => 'processing',
                 'reference' => $orderReference,
                 'transaction_id' => $result['id'] ?? null,
             ]);
         } catch (ClickPesaException $e) {
             // A specific, user-facing reason from ClickPesa itself — e.g.
             // "Insufficient funds in your Halopesa account. Please top up and
             // try again.", "Invalid / unsupported phone number", etc.
             // Safe to show directly to the customer.
             $payment->update(['status' => 'failed']);
     
             return response()->json([
                 'success' => false,
                 'message' => $e->getMessage(),
             ], 422);
         } catch (\Throwable $e) {
             // Unexpected failure (network issue, ClickPesa outage, etc.) —
             // don't expose internal details to the customer.
             $payment->update(['status' => 'failed']);
     
             return response()->json([
                 'success' => false,
                 'message' => 'Could not initiate payment with the provider. Please try again.',
             ], 502);
         }
     
         return response()->json([
             'success' => true,
             'message' => 'Payment initiated. Please check your phone to enter your PIN.',
             'data' => [
                 'payment_id' => $payment->id,
                 'order_id' => $payment->order_id,
                 'method' => $payment->method,
                 'amount' => $payment->amount,
                 'currency' => $payment->currency,
                 'status' => $payment->status,
             ],
         ], 201);
     }
}