<?php

namespace App\Support;

use App\Models\CardTerminal;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\DeliveryPlatform;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\PaymentGateway;
use App\Models\PaymentMerchant;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendConfig;
use App\Models\VendContract;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Central master-data option-cache invalidation.
 *
 * Several controllers cache dropdown/option lists (mostly 24h TTL in
 * VendController::customerIndex()/transactionIndex()/dailySummary()).
 * Before this class, editing the underlying master data (e.g. renaming a
 * Refilling Route / Zone) kept serving the stale cached name until the TTL
 * expired. Each listener below forgets exactly the cache keys derived from
 * the model that changed, so dropdowns reflect edits immediately.
 *
 * Design notes (perf-sensitive — see UserLogger::listen() disable note in
 * AppServiceProvider):
 * - Listeners are registered PER MODEL CLASS ("eloquent.saved: X"), never
 *   as wildcards, so high-frequency writers (vends sync, queue jobs) don't
 *   pay for models they never touch.
 * - Hot models (Vend, Product) guard with wasChanged() so routine telemetry
 *   / stock writes don't churn the cache.
 * - Mass query-builder updates (Model::query()->update()) bypass Eloquent
 *   events and therefore this class — all master-data admin CRUD in this
 *   app goes through Eloquent saves, keep it that way.
 */
class OptionCacheBuster
{
    /** Version-stamp key for the operator-combo product options cache. */
    public const PRODUCT_OPTIONS_VER_KEY = 'product_options_ver';

    /**
     * Models whose change invalidates fixed cache key(s).
     */
    protected const STATIC_KEYS = [
        DeliveryPlatform::class => ['delivery_platform_options'],
        LocationType::class     => ['location_type_options'],
        VendChannelError::class => ['vend_channel_errors'],
        VendConfig::class       => ['vend_config_options'],
        VendContract::class     => ['vend_contract_options'],
        VendModel::class        => ['vend_model_options'],
        Zone::class             => ['zone_options'], // "Refilling Routes" dropdown
        ProductMapping::class   => ['product_mapping_options'],
        CardTerminal::class     => ['card_terminal_options'],
        Tag::class              => ['tag_options_product'],
        User::class             => ['customer_driver_options'],
        PaymentMethod::class    => ['payment_methods', 'payment_method_id_credit_card'],
    ];

    public static function listen(): void
    {
        foreach (self::STATIC_KEYS as $model => $keys) {
            self::onChange($model, function () use ($keys) {
                foreach ($keys as $key) {
                    Cache::forget($key);
                }
            });
        }

        // operator_options_{operator_id} — keyed by the viewing user's
        // operator, content is ALL operators; bust every per-operator copy.
        self::onChange(Operator::class, fn () => self::forgetPerOperator('operator_options_'));

        // vend_prefix_options_active_{operator_id} — same per-operator
        // keying. Only busts on VendPrefix edits; the "has active vends"
        // part naturally refreshes via TTL (vend status flips constantly).
        self::onChange(VendPrefix::class, fn () => self::forgetPerOperator('vend_prefix_options_active_'));

        // categories_{classname} / category_groups_{classname} — bust the
        // changed row's classname plus every classname still in the table
        // (covers the common case; a classname fully renamed away simply
        // ages out via TTL — classnames are fixed per admin page here).
        self::onChange(Category::class, function ($model) {
            self::forgetPerClassname('categories_', 'categories', $model->classname);
        });
        self::onChange(CategoryGroup::class, function ($model) {
            self::forgetPerClassname('category_groups_', 'category_groups', $model->classname);
        });

        // payment_merchants:{countryCode}:{gatewayName} (machine-facing API,
        // PaymentController::getPaymentMerchantsApi) — derived from
        // PaymentMethod + PaymentGateway + PaymentMerchant.
        foreach ([PaymentMethod::class, PaymentGateway::class, PaymentMerchant::class] as $model) {
            self::onChange($model, fn () => self::forgetPaymentMerchantCombos());
        }

        // customer_product_options_{ver}_{operatorIds} — combo keys can't be
        // enumerated, so the key carries a version stamp; bumping it makes
        // every combo stale at once. Only bump when a column that actually
        // feeds the dropdown changed — Product rows are written by stock
        // syncs far more often than their option fields change.
        self::onChange(Product::class, function ($model) {
            if (
                $model->wasRecentlyCreated
                || !$model->exists // deleted
                || $model->wasChanged([
                    'code', 'desc', 'name', 'operator_id',
                    'is_active', 'is_inventory', 'is_available',
                    'is_available_updated_at', 'is_available_updated_by',
                ])
            ) {
                self::bumpProductOptionsVersion();
            }
        });

        // testing_vend_ids — used by Dashboard/exports/aggregators (up to 1h
        // TTL). Vend rows are saved constantly by machine sync, so guard hard:
        // only the is_testing flag flipping matters.
        Event::listen('eloquent.saved: ' . Vend::class, function (Vend $vend) {
            if ($vend->wasChanged('is_testing')) {
                Cache::forget('testing_vend_ids');
            }
        });
        Event::listen('eloquent.deleted: ' . Vend::class, function (Vend $vend) {
            if ($vend->is_testing) {
                Cache::forget('testing_vend_ids');
            }
        });
    }

    /**
     * Current version stamp for the product options cache key.
     */
    public static function productOptionsVersion(): int
    {
        return (int) (Cache::get(self::PRODUCT_OPTIONS_VER_KEY) ?? 1);
    }

    public static function bumpProductOptionsVersion(): void
    {
        // add() is a no-op when the key exists; together they behave as an
        // atomic "initialise then increment" on every supported cache store.
        Cache::add(self::PRODUCT_OPTIONS_VER_KEY, 1);
        Cache::increment(self::PRODUCT_OPTIONS_VER_KEY);
    }

    /**
     * Register the same callback for saved + deleted (covers create, update,
     * restore and delete; none of the mapped models use SoftDeletes).
     */
    protected static function onChange(string $modelClass, callable $callback): void
    {
        Event::listen('eloquent.saved: ' . $modelClass, $callback);
        Event::listen('eloquent.deleted: ' . $modelClass, $callback);
    }

    /**
     * Forget {prefix}{operator_id} for every operator (small table).
     */
    protected static function forgetPerOperator(string $prefix): void
    {
        foreach (DB::table('operators')->pluck('id') as $operatorId) {
            Cache::forget($prefix . $operatorId);
        }
    }

    /**
     * Forget {prefix}{classname} for the changed row's classname and every
     * distinct classname currently in the table.
     */
    protected static function forgetPerClassname(string $prefix, string $table, ?string $ownClassname): void
    {
        $classnames = DB::table($table)->distinct()->pluck('classname')->all();

        if ($ownClassname !== null) {
            $classnames[] = $ownClassname;
        }

        foreach (array_unique($classnames) as $classname) {
            Cache::forget($prefix . $classname);
        }
    }

    /**
     * Forget payment_merchants:{countryCode}:{gatewayName} for every
     * gateway/country pair on record (tiny tables).
     */
    protected static function forgetPaymentMerchantCombos(): void
    {
        $pairs = DB::table('payment_gateways')
            ->join('countries', 'countries.id', '=', 'payment_gateways.country_id')
            ->select('countries.code', 'payment_gateways.name')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            Cache::forget("payment_merchants:{$pair->code}:{$pair->name}");
        }
    }
}
