<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\ProductResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Show the authenticated user's cart.
     */
    public function show(Request $request)
{
    $cart = Cart::where('user_id', $request->user()->id)
        ->with([
            'items.product.category',
            'items.product.seller',
        ])
        ->first();

    if (! $cart) {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => null,
                'user_id' => $request->user()->id,
                'items' => [],
            ],
        ]);
    }

    $items = $cart->items->map(function ($item) {
        return [
            'id' => $item->id,
            'cart_id' => $item->cart_id,
            'quantity' => $item->quantity,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'product' => $item->product
                ? (new ProductResource($item->product))->resolve()
                : null,
        ];
    });

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
            'items' => $items,
        ],
    ]);
}

    /**
     * Add a product to the cart.
     */
    public function addItem(AddCartItemRequest $request)
    {
        $user = $request->user();

        $product = Product::where('id', $request->product_id)
            ->where('is_active', true)
            ->firstOrFail();

        $cart = Cart::firstOrCreate([
            'user_id' => $user->id,
        ]);

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->first();

        $currentQuantity = $item?->quantity ?? 0;

        $newQuantity = $currentQuantity + $request->integer('quantity');

        if ($newQuantity > $product->stock_quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough stock available.',
            ], 422);
        }

        if ($item) {
            $item->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $request->integer('quantity'),
            ]);
        }

        $item->load('product.category');

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart.',
            'data' => [
                'id' => $item->id,
                'cart_id' => $item->cart_id,
                'quantity' => $item->quantity,
                'product' => $item->product,
            ],
        ], 201);
    }

    /**
     * Update the quantity of a cart item.
     */
    public function updateItem(
        UpdateCartItemRequest $request,
        CartItem $item
    ) {
        $cart = Cart::where('user_id', $request->user()->id)
            ->findOrFail($item->cart_id);

        $product = Product::where('id', $item->product_id)
            ->where('is_active', true)
            ->firstOrFail();

        $quantity = $request->integer('quantity');

        if ($quantity > $product->stock_quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough stock available.',
            ], 422);
        }

        $item->update([
            'quantity' => $quantity,
        ]);

        $item->load('product.category');

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully.',
            'data' => [
                'id' => $item->id,
                'cart_id' => $item->cart_id,
                'quantity' => $item->quantity,
                'product' => $item->product,
            ],
        ]);
    }

    /**
     * Remove one item from the cart.
     */
    public function deleteItem(
        Request $request,
        CartItem $item
    ) {
        Cart::where('user_id', $request->user()->id)
            ->findOrFail($item->cart_id);

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from cart.',
        ]);
    }

    /**
     * Clear the authenticated user's cart.
     */
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'message' => 'Cart is already empty.',
            ]);
        }

        $cart->items()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully.',
        ]);
    }
}
