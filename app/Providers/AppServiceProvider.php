<?php

namespace App\Providers;

use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\ProductChild;
use App\Models\RefundTicket;
use App\Models\UnitCost;
use App\Observers\OpsJobItemChannelObserver;
use App\Observers\OpsJobItemObserver;
use App\Observers\ProductChildObserver;
use App\Observers\RefundTicketObserver;
use App\Observers\UnitCostObserver;
use App\Services\UserLogger;
use App\Support\OptionCacheBuster;
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
        // Keep the freeze work-queue in sync so ops:freeze-stock-in scans a tiny
        // table instead of the whole ops_job_items history.
        OpsJobItem::observe(OpsJobItemObserver::class);
        // Mirror each refund ticket's live status onto its matched sales
        // transaction (vend_transactions.refund_request_*) so the Transactions
        // page reads the "Refund Request" column without joining refund_tickets.
        RefundTicket::observe(RefundTicketObserver::class);

        // Master-data option caches (Refilling Routes / Zones, Location Types,
        // Contracts, Models, Payment Methods, etc.): forget the cached dropdown
        // payloads the moment the underlying row is created/updated/deleted so
        // edits show up immediately instead of after the 24h TTL. Per-model
        // listeners only — NOT a wildcard (see UserLogger note below).
        OptionCacheBuster::listen();

        // App-wide user-action audit log (web CRUD only; cron/queue/machine excluded).
        // TEMPORARILY DISABLED 2026-07-01 while investigating queue backlog — the
        // wildcard listener fired on every Eloquent write inside machine/queue jobs.
        // Re-enable after the gate is made queue-cheap (runningInConsole early-out).
        // UserLogger::listen();
    }
}
