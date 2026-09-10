<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Http\Requests\StorePaymentRequest;

class OrderController extends Controller
{

    public function index(Request $request)
{
    $orders = Order::where('user_id', $request->user()->id)
        ->with('items.product')
        ->latest()
        ->paginate(20);

    return response()->json([
        'success' => true,
        'data' => $orders,
    ]);
}

public function show(Request $request, Order $order)
{
    if ($order->user_id !== $request->user()->id) {
        abort(404);
    }

    $order->load('items.product');

    return response()->json([
        'success' => true,
        'data' => $order,
    ]);
}
    public function store(CheckoutRequest $request)
    {
        $user = $request->user();

        $order = DB::transaction(function () use ($user) {

            $cart = Cart::where('user_id', $user->id)
                ->with('items.product')
                ->lockForUpdate()
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                abort(422, 'Your cart is empty.');
            }

            $totalAmount = 0;

            foreach ($cart->items as $cartItem) {

                $product = Product::where('id', $cartItem->product_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    abort(
                        422,
                        "Product {$cartItem->product_id} is no longer available."
                    );
                }

                if ($cartItem->quantity > $product->stock_quantity) {
                    abort(
                        422,
                        "Not enough stock available for {$product->name}."
                    );
                }

                $unitPrice = $product->price;

                $itemTotal = $unitPrice * $cartItem->quantity;

                $totalAmount += $itemTotal;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $totalAmount,
            ]);

            foreach ($cart->items as $cartItem) {

                $product = Product::lockForUpdate()
                    ->find($cartItem->product_id);

                $unitPrice = $product->price;

                $itemTotal = $unitPrice * $cartItem->quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                ]);

                $product->decrement(
                    'stock_quantity',
                    $cartItem->quantity
                );
            }

            $cart->items()->delete();

            return $order;
        });

        $order->load('items.product');

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }
    

public function adminUpdateStatus(
    UpdateOrderStatusRequest $request,
    Order $order
) {
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
            'shipped',
            'cancelled',
        ],

        'shipped' => [
            'delivered',
        ],

        'delivered' => [],

        'cancelled' => [],
    ];

    if (!in_array(
        $newStatus,
        $allowedTransitions[$currentStatus] ?? [],
        true
    )) {
        return response()->json([
            'success' => false,
            'message' => "Cannot change order status from {$currentStatus} to {$newStatus}.",
        ], 422);
    }

    $order->update([
        'status' => $newStatus,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Order status updated successfully.',
        'data' => $order->fresh()->load('items.product.seller'),
    ]);
}

public function sellerOrders(Request $request)
{
    $sellerId = $request->user()->id;

    $orders = Order::whereHas('items.product', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
        ->with([
            'user',
            'items' => function ($query) use ($sellerId) {
                $query->whereHas('product', function ($productQuery) use ($sellerId) {
                    $productQuery->where('seller_id', $sellerId);
                });
            },
            'items.product',
        ])
        ->latest()
        ->paginate(20);

    return response()->json([
        'success' => true,
        'data' => $orders,
    ]);
}
public function sellerOrder(Request $request, Order $order)
{
    $sellerId = $request->user()->id;

    $hasSellerProduct = $order->items()
        ->whereHas('product', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
        ->exists();

    if (!$hasSellerProduct) {
        abort(404);
    }

    $order->load([
        'user',
        'items' => function ($query) use ($sellerId) {
            $query->whereHas('product', function ($productQuery) use ($sellerId) {
                $productQuery->where('seller_id', $sellerId);
            });
        },
        'items.product',
    ]);

    $sellerTotal = $order->items->sum('total_price');

    return response()->json([
        'success' => true,
        'data' => [
            'order_id' => $order->id,
            'customer' => [
                'id' => $order->user->id,
                'name' => $order->user->name,
            ],
            'status' => $order->status,
            'seller_total' => $sellerTotal,
            'items' => $order->items,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ],
    ]);
}


public function adminOrders(Request $request)
{
    $orders = Order::with([
            'user',
            'items.product.seller',
        ])
        ->latest()
        ->paginate(20);

    return response()->json([
        'success' => true,
        'data' => $orders,
    ]);
}
public function adminOrder(Order $order)
{
    $order->load([
        'user',
        'items.product.seller',
    ]);

    return response()->json([
        'success' => true,
        'data' => $order,
    ]);
}
public function cancel(Request $request, Order $order)
{
    // Make sure the order belongs to the logged-in customer.
    if ($order->user_id !== $request->user()->id) {
        abort(404);
    }

    $allowedStatuses = [
        'pending',
        'confirmed',
        'processing',
    ];

    if (!in_array($order->status, $allowedStatuses, true)) {
        return response()->json([
            'success' => false,
            'message' => "Order cannot be cancelled because its status is {$order->status}.",
        ], 422);
    }

    DB::transaction(function () use ($order) {

        $order->load('items');

        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)
                ->lockForUpdate()
                ->increment(
                    'stock_quantity',
                    $item->quantity
                );
        }

        $order->update([
            'status' => 'cancelled',
        ]);
    });

    return response()->json([
        'success' => true,
        'message' => 'Order cancelled successfully.',
        'data' => $order->fresh()->load('items.product'),
    ]);
}
public function payment(StorePaymentRequest $request, Order $order)
{
    // Make sure the customer owns this order
    if ($order->user_id !== $request->user()->id) {
        abort(404);
    }

    // Only unpaid orders can be paid
    $existingPayment = $order->payments()
        ->whereIn('status', ['pending', 'processing', 'paid'])
        ->latest()
        ->first();

    if ($existingPayment) {
        return response()->json([
            'success' => false,
            'message' => 'This order already has a payment in progress or has been paid.',
        ], 422);
    }

    // Only these order statuses can be paid
    if (!in_array($order->status, ['pending', 'confirmed'], true)) {
        return response()->json([
            'success' => false,
            'message' => "Order cannot be paid because its status is {$order->status}.",
        ], 422);
    }

    // Amount comes from the database, NOT the frontend
    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $request->user()->id,
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
            'order_id' => $order->id,
            'method' => $payment->method,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
        ],
    ], 201);
}



}
