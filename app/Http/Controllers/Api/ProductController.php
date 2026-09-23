<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateProductStockRequest;
use App\Http\Requests\UpdateProductStatusRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller {
    public function index( Request $request ) {
        $products = Product::query()
        ->where( 'is_active', true )
        ->with( [ 'category', 'seller' ] )
        ->latest();

        if ( $request->filled( 'category_id' ) ) {
            $products->where( 'category_id', $request->integer( 'category_id' ) );
        }

        return ProductResource::collection(
            $products->paginate( 20 )
        );
    }
      //seller products
    public function sellerProducts( Request $request ) {
        $sellerId = $request->user()->id;

        $products = Product::query()
        ->where( 'seller_id', $sellerId )
        ->with( [ 'category', 'seller' ] )
        ->latest()
        ->paginate( 20 );

        return ProductResource::collection( $products );
    }
    public function updateStock(
        UpdateProductStockRequest $request,
        Product $product
    ): ProductResource {
        $product->update([
            'stock_quantity' => $request->integer('stock_quantity'),
        ]);
    
        return new ProductResource(
            $product->fresh()->load(['category', 'seller'])
        );
    }
    public function store( StoreProductRequest $request ): ProductResource {
        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Calculate selling price
        |--------------------------------------------------------------------------
        */

        $oldPrice = ( float ) $validated[ 'old_price' ];
        $discount = ( float ) $validated[ 'discount' ];

        $price = round(
            $oldPrice - ( $oldPrice * $discount / 100 ),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Generate unique slug
        |--------------------------------------------------------------------------
        */

        $baseSlug = Str::slug( $validated[ 'name' ] );
        $slug = $baseSlug;
        $counter = 2;

        while ( Product::where( 'slug', $slug )->exists() ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        /*
        |--------------------------------------------------------------------------
        | Upload image
        |--------------------------------------------------------------------------
        */

        $imagePath = null;

        if ( $request->hasFile( 'image' ) ) {
            $imagePath = $request->file( 'image' )
            ->store( 'products', 'public' );
        }

        /*
        |--------------------------------------------------------------------------
        | Create product
        |--------------------------------------------------------------------------
        */

        $product = Product::create( [
            'category_id' => $validated[ 'category_id' ],
            'seller_id' => $request->user()->id,
            'name' => $validated[ 'name' ],
            'slug' => $slug,
            'description' => $validated[ 'description' ] ?? null,
            'image' => $imagePath,
            'old_price' => $oldPrice,
            'discount' => $discount,
            'price' => $price,
            'stock_quantity' => $validated[ 'stock_quantity' ],
            'is_active' => true,
        ] );

        return new ProductResource(
            $product->load( [ 'category', 'seller' ] )
        );
    }

    public function show( Product $product ): ProductResource {
        abort_if ( ! $product->is_active, 404 );

        return new ProductResource(
            $product->load( [ 'category', 'seller' ] )
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ): ProductResource {
        $user = $request->user();

        if ( ! Authorization::canManageProduct( $user, $product ) ) {
            abort( 403, 'You are not allowed to update this product.' );
        }

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Update slug if name changed
        |--------------------------------------------------------------------------
        */

        if ( isset( $validated[ 'name' ] ) ) {
            $baseSlug = Str::slug( $validated[ 'name' ] );
            $slug = $baseSlug;
            $counter = 2;

            while (
                Product::where( 'slug', $slug )
                ->where( 'id', '!=', $product->id )
                ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $validated[ 'slug' ] = $slug;
        }

        /*
        |--------------------------------------------------------------------------
        | Recalculate price only when pricing is updated
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists( 'old_price', $validated ) ||
            array_key_exists( 'discount', $validated )
        ) {
            $oldPrice = array_key_exists( 'old_price', $validated )
            ? ( float ) $validated[ 'old_price' ]
            : ( float ) $product->old_price;

            $discount = array_key_exists( 'discount', $validated )
            ? ( float ) $validated[ 'discount' ]
            : ( float ) ( $product->discount ?? 0 );

            $validated[ 'price' ] = round(
                $oldPrice - ( $oldPrice * $discount / 100 ),
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Replace image
        |--------------------------------------------------------------------------
        */

        if ( $request->hasFile( 'image' ) ) {
            if ( $product->image ) {
                Storage::disk( 'public' )->delete( $product->image );
            }

            $validated[ 'image' ] = $request->file( 'image' )
            ->store( 'products', 'public' );
        }

        /*
        |--------------------------------------------------------------------------
        | Update product
        |--------------------------------------------------------------------------
        */

        $product->update( $validated );

        return new ProductResource(
            $product->fresh()->load( [ 'category', 'seller' ] )
        );
    }

    public function destroy(
        Request $request,
        Product $product
    ) {
        $user = $request->user();

        if ( ! Authorization::canManageProduct( $user, $product ) ) {
            abort( 403, 'You are not allowed to delete this product.' );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete product image
        |--------------------------------------------------------------------------
        */

        if ( $product->image ) {
            Storage::disk( 'public' )->delete( $product->image );
        }

        $product->delete();

        return response()->json( [
            'success' => true,
            'message' => 'Product deleted successfully.',
        ] );
    }

    public function updateStatus(
        UpdateProductStatusRequest $request,
        Product $product
    ): ProductResource {
        $product->update( [
            'is_active' => $request->boolean( 'is_active' ),
        ] );

        return new ProductResource(
            $product->fresh()->load( [ 'category', 'seller' ] )
        );
    }
    public function deals()
    {
        $products = Product::with([
            'category',
            'seller'
        ])
            ->where('is_active', true)
            ->whereNotNull('discount')
            ->where('discount', '>', 0)
            ->whereNotNull('old_price')
            ->where('old_price', '>', 0)
            ->where('stock_quantity', '>', 0)
            ->orderByDesc('discount')
            ->latest()
            ->take(8)
            ->get();
    
        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ]);
    }
}