<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            'users.view',
            'users.update',
            'users.delete',

            // Sellers
            'sellers.view',
            'sellers.approve',
            'sellers.reject',
            'sellers.suspend',

            // Riders
            'riders.view',
            'riders.create',
            'riders.update',
            'riders.delete',
            'riders.activate',

            // Deliveries
            'deliveries.view',
            'deliveries.assign',
            'deliveries.update',
            'deliveries.manage',

            // Categories
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',

            // Products
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.manage-stock',
            'products.activate',

            // Cart
            'cart.view',
            'cart.manage',

            // Discounts
            'discounts.view',
            'discounts.create',
            'discounts.update',
            'discounts.delete',

            // Orders
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.manage',

            // Payments
            'payments.view',
            'payments.create',
            'payments.manage',

            // Reviews
            'reviews.view',
            'reviews.create',
            'reviews.update',
            'reviews.delete',

            // Delivery / confirmation
            'orders.confirm-delivery',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $seller = Role::findOrCreate('seller', 'web');
        $buyer = Role::findOrCreate('buyer', 'web');
        $rider = Role::findOrCreate('rider', 'web');

        // Admin
        $admin->givePermissionTo(Permission::all());

        // Seller
        $seller->givePermissionTo([
            'categories.view',
            'products.view',
            'products.create',
            'products.update',
            'products.activate',
            'products.manage-stock',

            'orders.view',
            'orders.update',
            'orders.manage',

            'reviews.view',
        ]);

        // Buyer
        $buyer->givePermissionTo([
            'categories.view',
            'products.view',

            'cart.view',
            'cart.manage',

            'orders.view',
            'orders.create',

            'payments.view',
            'payments.create',

            'reviews.view',
            'reviews.create',
            'reviews.update',

            'orders.confirm-delivery',
        ]);

        // Rider
        $rider->givePermissionTo([
            'deliveries.view',
            'deliveries.update',
        ]);
    }
}