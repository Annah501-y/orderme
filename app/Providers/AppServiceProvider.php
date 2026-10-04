<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ClickPesa restricts payout creation per merchant, so all workers share one limit.
        RateLimiter::for('clickpesa-payouts', fn () => Limit::perMinute(1)->by('clickpesa-merchant'));
    }
}
