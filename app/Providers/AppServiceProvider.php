<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // In production (Railway) the public connection is always HTTPS, so
        // generate https:// links and form actions to avoid "not secure"
        // browser warnings on form submits.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
