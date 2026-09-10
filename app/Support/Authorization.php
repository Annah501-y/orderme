<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;

class Authorization
{
    public static function canManageProduct(
        User $user,
        Product $product
    ): bool {
        return $user->hasRole('admin')
            || (
                $user->hasRole('seller')
                && $product->seller_id === $user->id
            );
    }
}
