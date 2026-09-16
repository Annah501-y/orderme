<?php

namespace App\Models;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\RiderInvitation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'profile_photo',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active'=> 'boolean',
        ];
    }
    public function cart(): HasOne
{
    return $this->hasOne(Cart::class);
}
public function orders(): HasMany
{
    return $this->hasMany(Order::class);
}
public function sellerProfile(): HasOne
{
    return $this->hasOne(SellerProfile::class);
}
public function wishlists():HasMany
{
    return $this->hasMany(Wishlist::class);
}
public function addresses():HasMany
{
    return $this->hasMany(Address::class);
}
public function products():HasMany
{
    return $this->hasMany(Product::class, 'seller_id');
}
public function riderInvitation():HasOne
{
return $this->hasOne(RiderInvitation::class);
}

}