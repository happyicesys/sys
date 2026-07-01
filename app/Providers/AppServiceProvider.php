<?php

namespace App\Providers;

use App\Models\OpsJobItemChannel;
use App\Models\ProductChild;
use App\Models\UnitCost;
use App\Observers\OpsJobItemChannelObserver;
use App\Observers\ProductChildObserver;
use App\Observers\UnitCostObserver;
use App\Services\UserLogger;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Inertia::share('initBinded', env('VEND_INIT_BINDED'));

        // Blind SKU: keep per-product blended unit costs in sync.
        UnitCost::observe(UnitCostObserver::class);
        ProductChild::observe(ProductChildObserver::class);
        // Blind SKU: snapshot the flavour set onto each ops job channel at creation.
        OpsJobItemChannel::observe(OpsJobItemChannelObserver::class);

        // App-wide user-action audit log (web CRUD only; cron/queue/machine excluded).
        // TEMPORARILY DISABLED 2026-07-01 while investigating queue backlog — the
        // wildcard listener fired on every Eloquent write inside machine/queue jobs.
        // Re-enable after the gate is made queue-cheap (runningInConsole early-out).
        // UserLogger::listen();
    }
}
