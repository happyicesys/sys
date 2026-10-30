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
use App\Support\MachineRefreshTriggers;
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
        // One UI dictionary for Vue and PHP: the Translations page's wording over lang/{locale}.json.
        $this->app->singleton(\App\Services\I18n\TranslationCatalog::class);
        $this->app->extend('translation.loader', fn ($loader, $app) => new \App\Services\I18n\OverridingTranslationLoader(
            $loader, $app->make(\App\Services\I18n\TranslationCatalog::class)
        ));

        // One registry per process: the dirty-day set's per-process dedupe and
        // the in-memory store used by tests both live on the instance.
        $this->app->singleton(\App\Services\Sales\DirtyDayRegistry::class);

        // Freezer welcome sketches are redrawn by OpenAI's image edit (inert without OPENAI_API_KEY).
        $this->app->bind(
            \App\Services\Products\WelcomeSketch\SketchGenerator::class,
            \App\Services\Products\WelcomeSketch\OpenAiSketchGenerator::class,
        );
        // ...and fall back to an open-source background cut-out (rembg) finished as a sticker.
        $this->app->bind(
            \App\Services\Products\WelcomeSketch\CutoutMaker::class,
            \App\Services\Products\WelcomeSketch\RembgCutout::class,
        );

        // One MQTT publisher connection per worker process (2026-09-25). SINGLETON
        // on purpose: the queue worker clears facades and scoped instances after
        // every job, and the old facade path opened a broker connection per
        // publish (~8/s). See App\Services\Mqtt\MqttPublisher.
        $this->app->singleton(\App\Services\Mqtt\MqttPublisher::class, fn () => new \App\Services\Mqtt\MqttPublisher(
            (array) config('mqtt_publisher'),
            (array) config('mqtt-client.connections'),
            (string) config('mqtt-client.default_connection'),
        ));

        // The banking calendar reads the public-holiday table once and keeps it
        // on the instance; a per-row instance would re-query it for every card
        // sale on a Sales Transactions page. SCOPED, not singleton: a Horizon
        // worker lives for days, and a singleton would serve a holiday table
        // frozen at boot long after the sync added a date to it.
        $this->app->scoped(\App\Services\CardSettlement\Payout\BankingCalendar::class);
        $this->app->scoped(\App\Services\CardSettlement\Payout\SettlementPayoutResolver::class);

        // Whose products an operator works with: asked by Product's global scope on
        // every product query, so memoised per request / job (scoped, see the class).
        $this->app->scoped(\App\Support\ProductOwners::class);

        // Which machines a user may see (VendAccess): asked by several global
        // scopes on every query, so resolved once per request / job.
        $this->app->scoped(\App\Support\VendAccessResolver::class);

        // Stock check "sync": one strategy per machine kind, first match wins.
        // A new machine kind that owns its stock differently is one more entry.
        $this->app->when(\App\Services\StockCheck\StockCheckService::class)
            ->needs('$syncTargets')
            ->give(fn ($app) => [
                $app->make(\App\Services\StockCheck\Sync\CityboxSyncTarget::class),
                $app->make(\App\Services\StockCheck\Sync\SystemQtySyncTarget::class),
            ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Say goodbye to the broker when a worker stops (deploy, horizon:terminate,
        // autoscaling) instead of leaving it to time the socket out.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\WorkerStopping::class, function () {
            if ($this->app->resolved(\App\Services\Mqtt\MqttPublisher::class)) {
                $this->app->make(\App\Services\Mqtt\MqttPublisher::class)->disconnectAll();
            }
        });
        // ...and when it has sat idle, instead of letting the broker time it out.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\Looping::class, function () {
            if ($this->app->resolved(\App\Services\Mqtt\MqttPublisher::class)) {
                $this->app->make(\App\Services\Mqtt\MqttPublisher::class)->closeIdleConnections();
            }
        });

        // Every failed exchange with Grab is kept 30 days, whatever code made the call
        // (Grab Errors page; App\Support\Grab\GrabApiErrorRecorder).
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ResponseReceived::class, function ($event) {
            if ($event->response->status() >= 400 && \App\Support\Grab\GrabApiErrorRecorder::isGrabUrl($event->request->url())) {
                \App\Support\Grab\GrabApiErrorRecorder::outgoing($event->request, $event->response);
            }
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ConnectionFailed::class, function ($event) {
            if (\App\Support\Grab\GrabApiErrorRecorder::isGrabUrl($event->request->url())) {
                \App\Support\Grab\GrabApiErrorRecorder::outgoing($event->request, null, $event->exception->getMessage());
            }
        });

        Inertia::share('initBinded', env('VEND_INIT_BINDED'));

        // Blind SKU: keep per-product blended unit costs in sync.
        UnitCost::observe(UnitCostObserver::class);
        \App\Models\Vend::observe(\App\Observers\VendPricingSourceObserver::class);
        ProductChild::observe(ProductChildObserver::class);
        // Blind SKU: snapshot the flavour set onto each ops job channel at creation.
        OpsJobItemChannel::observe(OpsJobItemChannelObserver::class);
        // Keep the freeze work-queue in sync so ops:freeze-stock-in scans a tiny
        // table instead of the whole ops_job_items history.
        OpsJobItem::observe(OpsJobItemObserver::class);
        OpsJobItem::observe(\App\Observers\CityboxOpsJobItemObserver::class);
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
        MachineRefreshTriggers::listen();

        // App-wide user-action audit log (web CRUD only; cron/queue/machine excluded).
        // Re-enabled 2026-07-12: UserLogger::listen() now short-circuits in console
        // contexts (runningInConsole early-out), so it never registers inside artisan
        // /cron/queue workers — the source of the 2026-07-01 backlog. On web requests
        // the per-write cost is the cheap Auth::guard('web') gate.
        UserLogger::listen();
    }
}
