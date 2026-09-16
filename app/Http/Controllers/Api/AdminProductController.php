<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    /**
     * Display all products for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with([
                'category',
                'seller',
                'seller.sellerProfile',
            ])
            ->latest();

        // Search by product name or description
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where(
                'category_id',
                $request->category_id
            );
        }

        // Filter by seller
        if ($request->filled('seller_id')) {
            $query->where(
                'seller_id',
                $request->seller_id
            );
        }

        // Filter by active/inactive status
        if ($request->filled('status')) {
            $query->where(
                'is_active',
                $request->status === 'active'
            );
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => [
                'products' => $products,
            ],
        ]);
    }

    /**
     * Display a single product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load([
            'category',
            'seller',
            'seller.sellerProfile',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data' => [
                'product' => $product,
            ],
        ]);
    }

    /**
     * Activate or deactivate a product.
     */
    public function updateStatus(
        Request $request,
        Product $product
    ): JsonResponse {
        $validated = $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $product->update([
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $product->is_active
                ? 'Product activated successfully.'
                : 'Product deactivated successfully.',
            'data' => [
                'product' => $product
                    ->fresh()
                    ->load([
                        'category',
                        'seller',
                        'seller.sellerProfile',
                    ]),
            ],
        ]);
    }
}