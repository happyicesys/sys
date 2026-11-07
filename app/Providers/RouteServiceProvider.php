<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/vends/customers';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::none();
        });

        RateLimiter::for('client', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Device card rail: per MACHINE, not per IP — freezers on carrier SIMs can share
        // one NAT address. A card attempt polls ~40/min; 120 leaves room for a retry burst.
        RateLimiter::for('device-card', function (Request $request) {
            return Limit::perMinute(120)->by('device-card:'.$request->route('code'));
        });

        // A driver keying a Grab order number in at the machine: per MACHINE (Vid), for the same NAT reason.
        // A real driver tries a few times; 30 a minute stops someone working through order numbers at one
        // machine to collect another customer's order (audit 2026-11-07 GRAB-01).
        RateLimiter::for('grab-order-lookup', function (Request $request) {
            return Limit::perMinute(30)->by('grab-order-lookup:'.(string) $request->input('Vid'));
        });
    }
}
