<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Get statistics for the admin dashboard.
     */
    public function index(): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        $totalUsers = User::count();

        $totalBuyers = User::whereHas('roles', function ($query) {
            $query->where('name', 'buyer');
        })->count();

        $totalSellers = User::whereHas('roles', function ($query) {
            $query->where('name', 'seller');
        })->count();

        $totalAdmins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->count();


        /*
        |--------------------------------------------------------------------------
        | SELLER APPLICATIONS
        |--------------------------------------------------------------------------
        */

        $pendingSellerApplications = SellerProfile::where(
            'status',
            'pending'
        )->count();

        $approvedSellerApplications = SellerProfile::where(
            'status',
            'approved'
        )->count();

        $rejectedSellerApplications = SellerProfile::where(
            'status',
            'rejected'
        )->count();


        /*
        |--------------------------------------------------------------------------
        | PRODUCTS
        |--------------------------------------------------------------------------
        */

        $totalProducts = Product::count();

        $activeProducts = Product::where(
            'is_active',
            true
        )->count();

        $inactiveProducts = Product::where(
            'is_active',
            false
        )->count();

        $outOfStockProducts = Product::where(
            'stock_quantity',
            0
        )->count();


        /*
        |--------------------------------------------------------------------------
        | ORDERS
        |--------------------------------------------------------------------------
        */

        $totalOrders = Order::count();

        $totalRevenue = Order::sum('total_amount');


        /*
        |--------------------------------------------------------------------------
        | ORDERS BY STATUS
        |--------------------------------------------------------------------------
        */

        $ordersByStatus = Order::query()
            ->select(
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('status')
            ->orderBy('status')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | ORDERS BY MONTH
        |--------------------------------------------------------------------------
        |
        | PostgreSQL uses TO_CHAR() for formatting dates.
        |
        */

        $ordersByMonth = Order::query()
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->where(
                'created_at',
                '>=',
                now()->subMonths(11)->startOfMonth()
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | PRODUCTS BY CATEGORY
        |--------------------------------------------------------------------------
        */

        $productsByCategory = Product::query()
            ->join(
                'categories',
                'products.category_id',
                '=',
                'categories.id'
            )
            ->select(
                'categories.name as category',
                DB::raw('COUNT(products.id) as products')
            )
            ->groupBy(
                'categories.id',
                'categories.name'
            )
            ->orderByDesc('products')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | RECENT ORDERS
        |--------------------------------------------------------------------------
        */

        $recentOrders = Order::with('user')
            ->latest()
            ->take(5)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | RECENT SELLER APPLICATIONS
        |--------------------------------------------------------------------------
        */

        $recentSellerApplications = SellerProfile::with('user')
            ->latest()
            ->take(5)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [

                /*
                |--------------------------------------------------------------------------
                | SUMMARY
                |--------------------------------------------------------------------------
                */

                'summary' => [
                    'total_users' => $totalUsers,

                    'total_buyers' => $totalBuyers,

                    'total_sellers' => $totalSellers,

                    'total_admins' => $totalAdmins,

                    'total_products' => $totalProducts,

                    'active_products' => $activeProducts,

                    'inactive_products' => $inactiveProducts,

                    'out_of_stock_products' => $outOfStockProducts,

                    'total_orders' => $totalOrders,

                    'total_revenue' => $totalRevenue,

                    'pending_seller_applications' =>
                        $pendingSellerApplications,

                    'approved_seller_applications' =>
                        $approvedSellerApplications,

                    'rejected_seller_applications' =>
                        $rejectedSellerApplications,
                ],


                /*
                |--------------------------------------------------------------------------
                | GRAPH DATA
                |--------------------------------------------------------------------------
                */

                'orders_by_status' => $ordersByStatus,

                'orders_by_month' => $ordersByMonth,

                'products_by_category' => $productsByCategory,


                /*
                |--------------------------------------------------------------------------
                | RECENT DATA
                |--------------------------------------------------------------------------
                */

                'recent_orders' => $recentOrders,

                'recent_seller_applications' =>
                    $recentSellerApplications,
            ],
        ]);
    }
}