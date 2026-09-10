<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display the authenticated buyer's wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)
            ->with('product')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wishlist,
        ]);
    }

    /**
     * Add a product to the authenticated buyer's wishlist.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        // Only active products can be wishlisted.
        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This product is no longer available.',
            ], 422);
        }

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        if (!$wishlist->wasRecentlyCreated) {
            return response()->json([
                'success' => false,
                'message' => 'This product is already in your wishlist.',
            ], 422);
        }

        $wishlist->load('product');

        return response()->json([
            'success' => true,
            'message' => 'Product added to your wishlist.',
            'data' => $wishlist,
        ], 201);
    }

    /**
     * Remove a product from the authenticated buyer's wishlist.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'This product is not in your wishlist.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product removed from your wishlist.',
        ]);
    }

    /**
     * Check whether a product is in the authenticated buyer's wishlist.
     */
    public function check(Request $request, Product $product): JsonResponse
    {
        $exists = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'is_wishlisted' => $exists,
            ],
        ]);
    }
}