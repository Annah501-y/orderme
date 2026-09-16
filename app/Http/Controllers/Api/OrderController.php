<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        $order = DB::transaction(function () use ($user) {

            $cart = Cart::where('user_id', $user->id)
                ->with('items.product')
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                abort(422, 'Your cart is empty.');
            }

            /*
             * Validate products and stock.
             */
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                if (! $product) {
                    abort(422, 'One of the products in your cart no longer exists.');
                }

                if (! $product->is_active) {
                    abort(
                        422,
                        "The product {$product->name} is no longer available."
                    );
                }

                if ($cartItem->quantity > $product->stock_quantity) {
                    abort(
                        422,
                        "Insufficient stock for {$product->name}."
                    );
                }
            }

            /*
             * Calculate total.
             */
            $totalAmount = $cart->items->sum(function ($cartItem) {
                return $cartItem->quantity * $cartItem->product->price;
            });

            /*
             * Create main order.
             */
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $totalAmount,
            ]);

            /*
             * Create order items and reduce stock.
             */
            foreach ($cart->items as $cartItem) {

                $product = $cartItem->product;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $product->price,
                    'total_price' => $cartItem->quantity * $product->price,
                ]);

                $product->decrement(
                    'stock_quantity',
                    $cartItem->quantity
                );
            }

            /*
             * Group cart items by seller.
             */
            $sellerTotals = [];

            foreach ($cart->items as $cartItem) {

                $sellerId = $cartItem->product->seller_id;

                $subtotal =
                    $cartItem->quantity *
                    $cartItem->product->price;

                if (! isset($sellerTotals[$sellerId])) {
                    $sellerTotals[$sellerId] = 0;
                }

                $sellerTotals[$sellerId] += $subtotal;
            }

            /*
             * Create one seller order per seller.
             */
            foreach ($sellerTotals as $sellerId => $sellerTotal) {

                SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'status' => 'pending',
                    'seller_total' => $sellerTotal,
                ]);
            }

            /*
             * Clear cart after successful checkout.
             */
            $cart->items()->delete();

            return $order;
        });

        $order->load([
            'items.product.seller',
            'sellerOrders.seller',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
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
        Order $order
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
            'provider' => 'clickpesa',
            'method' => $request->input('method'),
            'amount' => $order->total_amount,
            'currency' => 'TZS',
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment initiated successfully.',
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