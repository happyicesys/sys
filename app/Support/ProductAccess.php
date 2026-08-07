<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Access Product(s)" — resolves WHICH products the current viewer may see.
 *
 * This is the product-dimension twin of the machine allow-list (user_vend).
 * Two allow-lists compose, and the Operator one is a hard ceiling:
 *
 *   Operator {aaa,bbb} + User unrestricted  => {aaa,bbb}   (inherits the ceiling)
 *   Operator {aaa,bbb} + User {aaa,ccc}     => {aaa}       (intersection)
 *   Operator {aaa,bbb} + User {ccc}         => []          (sees NOTHING)
 *   Operator unrestricted + User unrestricted => null      (sees everything)
 *
 * ==========================================================================
 * READ THIS BEFORE TOUCHING ANY CALLER
 * ==========================================================================
 * null and [] mean OPPOSITE things.
 *
 *      null  = unrestricted, add no predicate at all
 *      []    = restricted to nothing, the query must return zero rows
 *
 * Never test these with truthiness. `if ($ids)` is false for BOTH, which
 * turns "sees nothing" into "sees everything" - a silent data leak. Always
 * compare `!== null`. (The machine allow-list does use truthiness; it gets
 * away with it only because it has no "restricted to nothing" state.)
 * ==========================================================================
 */
class ProductAccess
{
    /** Sentinel for "we resolved this user and the answer was unrestricted". */
    private const UNRESTRICTED = 'unrestricted';

    public const MODE_ALL = 'all';

    public const MODE_LIST = 'list';

    /**
     * Per-request memo, keyed by user id. Mandatory: a global scope runs on
     * every Eloquent query in the request, so this must never re-query.
     *
     * @var array<int, array<int, int>|string>
     */
    private static array $memo = [];

    /**
     * Effective allowed product ids for a user.
     *
     * @return array<int, int>|null null = unrestricted, [] = sees nothing
     */
    public static function forUser(User $user): ?array
    {
        $key = (int) $user->getKey();

        if (array_key_exists($key, self::$memo)) {
            $cached = self::$memo[$key];

            return $cached === self::UNRESTRICTED ? null : $cached;
        }

        try {
            $resolved = self::resolve($user);
        } catch (Throwable $e) {
            // Fail OPEN, deliberately.
            //
            // This runs inside global scopes, i.e. on essentially every query in
            // the app. If the tables/columns this reads are missing - the code
            // deployed ahead of `php artisan migrate` - throwing here would take
            // the whole site down. Returning null means "unrestricted", which is
            // exactly how the app behaved before this feature existed.
            //
            // The trade-off is explicit: a broken migration state degrades to
            // pre-feature behaviour rather than an outage. Logged once per user
            // per request so it is visible rather than silent.
            Log::warning('ProductAccess resolution failed; treating viewer as unrestricted', [
                'user_id' => $key,
                'error' => $e->getMessage(),
            ]);

            $resolved = null;
        }

        self::$memo[$key] = $resolved === null ? self::UNRESTRICTED : $resolved;

        return $resolved;
    }

    /**
     * Effective allowed product ids for whoever is logged in right now.
     *
     * Returns null (unrestricted) for anything that is not a logged-in web
     * User: machines on auth:api, the public refund form, queue workers, cron.
     * Those paths MUST stay unfiltered - a partial planogram would break a
     * vending machine.
     *
     * @return array<int, int>|null
     */
    public static function current(): ?array
    {
        if (! auth()->check()) {
            return null;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return self::forUser($user);
    }

    /** Is the current viewer product-restricted at all? */
    public static function isRestricted(): bool
    {
        return self::current() !== null;
    }

    /**
     * The operator-level ceiling, used by the User edit screen to cap the
     * dropdown and render the "capped by operator" badge.
     *
     * @return array<int, int>|null
     */
    public static function operatorCeiling(?int $operatorId): ?array
    {
        if (! $operatorId || self::isOperatorExempt($operatorId)) {
            return null;
        }

        try {
            return self::operatorSet($operatorId);
        } catch (Throwable $e) {
            // Same fail-open rationale as forUser().
            return null;
        }
    }

    /**
     * Apply the product predicate to a vend_transactions query.
     *
     * Pass $ids explicitly from queue jobs (auth() is empty there); pass null
     * for $ids from web code to have it resolved from the session.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  array<int, int>|null  $ids
     */
    public static function applyToVendTransactions($query, ?array $ids = null, string $table = 'vend_transactions')
    {
        if (func_num_args() < 2) {
            $ids = self::current();
        }

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw(self::transactionPredicate($table, $ids));
    }

    /**
     * Apply the restriction to any table that carries a plain product_id
     * (products.id, vend_product_records.product_id, gp_metrics.product_id,
     * vend_channels.product_id, ...).
     *
     * whereIn drops NULLs for free, which is what we want: an unmapped channel
     * or an unattributable aggregate row is not the restricted viewer's.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  array<int, int>|null  $ids
     */
    public static function applyToColumn($query, string $column, ?array $ids = null)
    {
        if (func_num_args() < 3) {
            $ids = self::current();
        }

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * A ready-to-interpolate SQL fragment restricting a vend_channels read.
     *
     * For the raw DB::raw() derived tables that back the Ops Dashboard's stock
     * columns - no Eloquent scope can reach inside those, so the restriction has
     * to be spliced into the SQL text.
     *
     * Returns '' when unrestricted (so the SQL is byte-identical to today for
     * every normal user), ' AND 1 = 0' when restricted to nothing.
     *
     * Channels with a NULL product_id are excluded along with everyone else's:
     * an unmapped slot's stock is not the restricted viewer's.
     *
     * @param  array<int, int>|null  $ids  omit to resolve from the session
     */
    public static function channelSqlFilter(string $table = 'vend_channels', ?array $ids = null): string
    {
        if (func_num_args() < 2) {
            $ids = self::current();
        }

        if ($ids === null) {
            return '';
        }

        if ($ids === []) {
            return ' AND 1 = 0';
        }

        return ' AND ' . $table . '.product_id IN (' . self::idList($ids) . ')';
    }

    /**
     * The self-contained WHERE fragment for vend_transactions.
     *
     * Correlated EXISTS only, no JOINs, so it is safe inside a global scope,
     * safe when the outer query already joined vend_channels unaliased, and
     * safe under count()/sum()/paginate(). Aliases are suffixed _pa to avoid
     * colliding with the export/report joins.
     *
     * Three legs:
     *   1. header product_id                       (single purchases)
     *   2. channel product, ONLY when the header is NULL
     *   3. any item in the basket                  (multi purchases)
     *
     * Legs 1+2 together are exactly COALESCE(vt.product_id, vc.product_id),
     * the fallback idiom used by VendTransactionSalesAggregator,
     * StoreVendProductRecords and ReportController. The "IS NULL" guard on
     * leg 2 matters: 6.9% of singles have a header product that differs from
     * the channel's CURRENT product because the planogram was remapped after
     * the sale. A flat OR would hand those rows to the wrong party.
     *
     * Leg 3 is what makes a mixed basket visible in full. Multi rows always
     * carry a NULL header product and vend_channel_id = 0 (verified over
     * 14,243 live rows), and no vend_channels row has id 0, so legs 1 and 2
     * can never fire for a multi. That is why there is no is_multiple branch
     * anywhere in this feature.
     *
     * @param  array<int, int>  $ids  must be non-empty
     */
    public static function transactionPredicate(string $table, array $ids): string
    {
        $list = self::idList($ids);

        return "(
            {$table}.product_id IN ({$list})
            OR (
                {$table}.product_id IS NULL
                AND EXISTS (
                    SELECT 1 FROM vend_channels vc_pa
                    WHERE vc_pa.id = {$table}.vend_channel_id
                      AND vc_pa.product_id IN ({$list})
                )
            )
            OR EXISTS (
                SELECT 1 FROM vend_transaction_items vti_pa
                WHERE vti_pa.vend_transaction_id = {$table}.id
                  AND (
                      vti_pa.product_id IN ({$list})
                      OR (
                          vti_pa.product_id IS NULL
                          AND EXISTS (
                              SELECT 1 FROM vend_channels vc_pa2
                              WHERE vc_pa2.id = vti_pa.vend_channel_id
                                AND vc_pa2.product_id IN ({$list})
                          )
                      )
                  )
            )
        )";
    }

    /**
     * Drop channels the viewer's allow-list excludes, from a DENORMALISED
     * vends.vend_channels_json blob.
     *
     * Handles every shape the app hands us: an already-cast array (VendResource,
     * where the model casts the column to 'json'), a stdClass array from a bare
     * json_decode (VendDBResource / VendSnapshotDBResource, which read the raw
     * string off a DB::table() row), or null.
     *
     * Returns the blob untouched when the viewer is unrestricted, so this is a
     * no-op for everyone without an allow-list.
     *
     * @param  mixed  $channels
     * @return mixed
     */
    public static function filterChannelsJson($channels)
    {
        $allowed = self::current();

        if ($allowed === null || ! is_array($channels)) {
            return $channels;
        }

        $filtered = array_filter($channels, function ($channel) use ($allowed) {
            // Array shape (cast) vs object shape (json_decode without assoc).
            if (is_array($channel)) {
                $productId = $channel['product']['id'] ?? null;
            } elseif (is_object($channel)) {
                $productId = $channel->product->id ?? null;
            } else {
                return false;
            }

            return self::allows($allowed, $productId);
        });

        return array_values($filtered);
    }

    /**
     * Strip the MONEY out of a whole-machine / whole-site rollup blob.
     *
     * vend_transaction_totals_json (and its site twin customers.totals_json)
     * mix two very different things:
     *
     *   money  - *_amount, *_revenue, *_gross_profit, *_average       -> removed
     *   health - *_count, *_all_count, *_error_count, *_error_rate    -> kept
     *
     * The money is summed over EVERY product in the machine, so it describes
     * other parties' revenue as much as the viewer's and there is no
     * per-product share to derive. The error rates and transaction counts are
     * machine health, which a restricted viewer is explicitly allowed to see -
     * nulling the whole blob (the first cut of this) took out the "Today
     * Error %" tile on Operations > Dashboard (Lite) along with the revenue.
     *
     * Note this returns an EMPTY blob rather than null when every key is
     * money, so callers that gate on `if (json)` keep working; the individual
     * `'key' in json` guards the frontend already uses then fail per key,
     * which is exactly the desired per-figure blanking.
     *
     * Handles both shapes: array (Eloquent 'json' cast) and stdClass
     * (json_decode without assoc, as the *DBResource twins do).
     *
     * @param  mixed  $totals
     * @return mixed
     */
    public static function maskWholeMachineJson($totals)
    {
        if (self::current() === null || $totals === null) {
            return $totals;
        }

        if (is_object($totals)) {
            $clone = new \stdClass;
            foreach (get_object_vars($totals) as $key => $value) {
                if (! self::isMoneyKey($key)) {
                    $clone->{$key} = $value;
                }
            }

            return $clone;
        }

        if (! is_array($totals)) {
            return null;
        }

        $kept = [];
        foreach ($totals as $key => $value) {
            if (! self::isMoneyKey((string) $key)) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    /**
     * Does this rollup key hold currency?
     *
     * Substring match, deliberately: every money key written by
     * Jobs\Vend\SyncVendTransactionTotalsJson contains one of these words
     * (today_amount, thirty_days_revenue, thirty_days_gross_profit,
     * vend_records_amount_average_day, last_2_mth_amount, ...) and every
     * non-money key contains none (today_count, one_day_error_rate,
     * seven_days_all_count, ...). A new money key added later is masked by
     * default, which is the safe direction to fail.
     */
    public static function isMoneyKey(string $key): bool
    {
        foreach (['amount', 'revenue', 'profit', 'earning', 'cost', 'fee'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this single product visible to the given id set? Used for per-row
     * cost masking in the exports, where the basket is visible but individual
     * item cost prices are not.
     *
     * @param  array<int, int>|null  $ids
     */
    public static function allows(?array $ids, $productId): bool
    {
        if ($ids === null) {
            return true;
        }

        if ($productId === null) {
            return false;
        }

        return in_array((int) $productId, $ids, true);
    }

    /**
     * The product a basket ITEM row actually represents.
     *
     * Mirrors leg 3 of the SQL predicate: the item's own product_id, falling
     * back to its channel's product when the item row carries none. Without the
     * fallback an item whose product_id was never populated reads as "not
     * yours" even when the channel it came out of is.
     *
     * The caller must have loaded vendChannel WITH product_id for the fallback
     * to fire; the export jobs select it explicitly.
     *
     * @param  mixed  $item  a VendTransactionItem
     */
    public static function itemProductId($item): ?int
    {
        $productId = $item->product_id ?? null;

        if ($productId !== null) {
            return (int) $productId;
        }

        $channelProductId = $item->vendChannel->product_id ?? null;

        return $channelProductId !== null ? (int) $channelProductId : null;
    }

    /**
     * May the viewer see this basket item's own detail?
     *
     * The BASKET stays whole either way - a mixed purchase is still listed in
     * full, header amount included, so the discount on a future basket-level
     * campaign stays explicable. This governs the per-item detail only: which
     * product it was, what it sold for and what it cost. A restricted viewer
     * therefore sees the true basket total but only their own share itemised.
     *
     * Single source of truth for all four renderers of a basket item -
     * VendTransactionItemResource (the on-screen table), both CSV export jobs
     * and the Excel export. They drifted apart once already: the screen blanked
     * a foreign item while the CSV named it. Add a fifth renderer, call this.
     *
     * @param  array<int, int>|null  $ids
     * @param  mixed  $item
     */
    public static function allowsItem(?array $ids, $item): bool
    {
        return self::allows($ids, self::itemProductId($item));
    }

    /** Drop a memoised user (call after writing either pivot). */
    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$memo = [];

            return;
        }

        unset(self::$memo[$userId]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array<int, int>|null
     */
    private static function resolve(User $user): ?array
    {
        $operatorId = $user->operator_id ? (int) $user->operator_id : null;

        // Operators listed here are never capped, whatever their pivot says.
        // Escape hatch for the HappyIce operator, which is the superuser group
        // everywhere else in this codebase.
        if ($operatorId && self::isOperatorExempt($operatorId)) {
            $operatorSet = null;
        } else {
            $operatorSet = $operatorId ? self::operatorSet($operatorId) : null;
        }

        $userSet = $user->product_access_mode === self::MODE_LIST
            ? self::expand(
                DB::table('product_user')
                    ->where('user_id', $user->getKey())
                    ->pluck('product_id')
                    ->all()
            )
            : null;

        if ($operatorSet === null && $userSet === null) {
            return null;
        }

        if ($operatorSet === null) {
            return $userSet;
        }

        if ($userSet === null) {
            return $operatorSet;
        }

        // Intersection may legitimately be empty => sees nothing.
        return array_values(array_intersect($userSet, $operatorSet));
    }

    /**
     * @return array<int, int>|null
     */
    private static function operatorSet(int $operatorId): ?array
    {
        $mode = DB::table('operators')->where('id', $operatorId)->value('product_access_mode');

        if ($mode !== self::MODE_LIST) {
            return null;
        }

        return self::expand(
            DB::table('operator_product')
                ->where('operator_id', $operatorId)
                ->pluck('product_id')
                ->all()
        );
    }

    private static function isOperatorExempt(int $operatorId): bool
    {
        return in_array($operatorId, (array) config('access.product_unrestricted_operator_ids', []), true);
    }

    /**
     * Granting a blind-SKU parent implicitly grants its ACTIVE children.
     *
     * A channel is mapped to the parent at master level, but the dispensed
     * transaction can record the child flavour, so without this the grant
     * silently under-matches. Expansion happens here and NOT in the pivot, so
     * the stored grant stays exactly what the admin picked.
     *
     * Deliberately one level and downward only - granting a child must never
     * grant the parent, whose totals aggregate the other flavours' money.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private static function expand(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $children = DB::table('product_children')
            ->whereIn('parent_product_id', $ids)
            ->where('is_active', true)
            ->pluck('child_product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($ids, $children)));
    }

    /**
     * @param  array<int, int>  $ids
     */
    private static function idList(array $ids): string
    {
        // Cast-to-int then implode: these are never user-supplied strings, they
        // come from our own pivot tables, but the predicate is inlined into raw
        // SQL so the cast is the guarantee rather than a convention.
        return implode(',', array_map('intval', $ids));
    }
}
