<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Customers can view their own orders.
        if ($order->user_id === $user->id) {
            return true;
        }

        // Admins can view any order.
        if ($user->hasRole('admin')) {
            return true;
        }

        // Sellers can view orders containing their products.
        return $order->items()
            ->whereHas('product', function ($query) use ($user) {
                $query->where('seller_id', $user->id);
            })
            ->exists();
    }

    /**
     * Determine whether the user can create an order.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // Admin can update any order.
        if ($user->hasRole('admin')) {
            return true;
        }

        // Seller can update only orders containing
        // at least one of their own products.
        if ($user->hasRole('seller')) {
            return $order->items()
                ->whereHas('product', function ($query) use ($user) {
                    $query->where('seller_id', $user->id);
                })
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }
}

