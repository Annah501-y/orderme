<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Anyone can browse products.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Anyone can view an individual product.
     */
    public function view(): bool
    {
        return true;
    }

    /**
     * A user needs products.create permission to create a product.
     */
    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    /**
     * Admins can update any product.
     *
     * Sellers can update only their own products and
     * must have the products.update permission.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update')
            && (
                $user->hasRole('admin')
                || $product->seller_id === $user->id
            );
    }

    /**
     * Admins can delete any product.
     *
     * Sellers can delete only their own products and
     * must have the products.delete permission.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete')
            && (
                $user->hasRole('admin')
                || $product->seller_id === $user->id
            );
    }

    public function restore(User $user, Product $product): bool
    {
        return false;
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}