<?php

namespace App\Services\Access;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Services\UserLogger;
use App\Support\ProductAccess;
use App\Support\ProductScopedSales;
use App\Support\VendAccess;
use App\Support\VendCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The machine level of a product-limited user, as User edit shows and sets it
 * (Brian, 2026-10-27: "based on Access Product, show which machines will be
 * shown to the Product Owner, then 2 buttons: Show (default), No Show").
 *
 * The list is LIVE — the active machines carrying any of the user's products
 * right now (an active channel with capacity, ProductAccess::vendHasChannelSql's
 * definition) — plus every machine already switched to No Show, so a hidden
 * machine that stopped carrying them can still be switched back. The only
 * writer of `user_hidden_vends`; VendAccess reads it.
 */
class UserMachineVisibility
{
    /**
     * Each row also carries the figures the table shows beside it (Brian, 2026-10-27:
     * Prefix, Machine L30d sales, SKU start date, SKU L30d sales) unless $withFigures is
     * false — the visibility POST checks membership with the bare list.
     *
     * @return array<int, array{id: int, code: string, prefix: ?string, site: ?string, sku_start_date: ?string, sku_start_dates: array<string, string>, products: array<int, string>, is_active: bool, is_shown: bool, carries: bool, machine_l30d: ?array{amount: int, count: int}, sku_l30d: ?array{amount: int, count: int}}>|null
     *                                                                                                                                                                                                                                                                                                                                null when the user is not limited to a product list
     */
    public function listFor(User $user, bool $withFigures = true): ?array
    {
        if ($user->product_access_mode !== ProductAccess::MODE_LIST) {
            return null;
        }

        $productIds = ProductAccess::forUser($user) ?? [];
        $hidden = $this->hiddenIds($user);

        if ($productIds === [] && $hidden === []) {
            return [];
        }

        $carrying = DB::table('vend_channels')
            ->join('products', 'products.id', '=', 'vend_channels.product_id')
            ->whereIn('vend_channels.product_id', $productIds ?: [0])
            ->where('vend_channels.is_active', 1)
            ->where('vend_channels.capacity', '>', 0)
            ->select('vend_channels.vend_id', 'products.code')
            ->distinct()
            ->get()
            ->groupBy('vend_id')
            ->map(fn ($rows) => $rows->pluck('code')->sort()->values()->all());

        $query = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereIn('vends.id', $carrying->keys()->all() ?: [0])->where('vends.is_active', true))
                ->orWhereIn('vends.id', $hidden ?: [0]))
            ->selectRaw(VendCode::sqlLabel().' as code_label')
            ->addSelect('vends.id', 'vends.code', 'vends.is_active', 'customers.name as site_name')
            ->addSelect('vend_prefixes.name as prefix_name', 'vends.vend_transaction_totals_json');

        // The machines this user could see at all: their own operator's, or every
        // operator's for HappyIce — and never more than the editor may see.
        if ((int) $user->operator_id !== OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID) {
            $query->where('vends.operator_id', $user->operator_id);
        }
        if ($editorOperatorId = OperatorVendFilterScope::viewerOperatorId()) {
            $query->where('vends.operator_id', $editorOperatorId);
        }

        $vends = $query->orderBy('vends.code')->get();
        $figures = $withFigures ? $this->figures($vends, $productIds) : null;
        $skuStarts = $withFigures ? $this->skuStartDates($vends->pluck('id')->map(fn ($id) => (int) $id)->all(), $productIds) : [];

        return $vends
            ->map(fn ($vend) => [
                'id' => (int) $vend->id,
                'code' => $vend->code_label,
                // vend_prefixes.name, as Sales Transactions and Warehouse Qty show it.
                'prefix' => $vend->prefix_name,
                'site' => $vend->site_name,
                // When each of their products went into this machine (skuStartDates);
                // the earliest one sorts the column.
                'sku_start_dates' => $skuStarts[(int) $vend->id] ?? [],
                'sku_start_date' => isset($skuStarts[(int) $vend->id]) ? min($skuStarts[(int) $vend->id]) : null,
                'products' => $carrying->get($vend->id, []),
                'is_active' => (bool) $vend->is_active,
                'is_shown' => ! in_array((int) $vend->id, $hidden, true),
                'carries' => $carrying->has($vend->id),
                'machine_l30d' => $figures['machine'][(int) $vend->id] ?? null,
                'sku_l30d' => $figures['sku'][(int) $vend->id] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Last-30-days sales per listed machine: the whole machine (its own
     * vend_transaction_totals_json, the Operation Dashboard's L30d) and this
     * user's products there (ProductScopedSales, same window).
     *
     * The EDITOR's own product limit still applies: an editor limited to a product
     * list never sees a whole-machine figure (it would show other suppliers'
     * sales), and the SKU figure counts only products both may see.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $vends
     * @param  array<int, int>  $productIds
     * @return array{machine: array<int, ?array{amount: int, count: int}>, sku: array<int, ?array{amount: int, count: int}>}
     */
    private function figures($vends, array $productIds): array
    {
        $editorProducts = ProductAccess::current();
        $vendIds = $vends->pluck('id')->map(fn ($id) => (int) $id)->all();

        $machine = [];
        if ($editorProducts === null) {
            foreach ($vends as $vend) {
                $json = is_string($vend->vend_transaction_totals_json)
                    ? json_decode($vend->vend_transaction_totals_json, true)
                    : (array) ($vend->vend_transaction_totals_json ?? []);
                $machine[(int) $vend->id] = is_array($json) && array_key_exists('thirty_days_amount', $json)
                    ? ['amount' => (int) $json['thirty_days_amount'], 'count' => (int) ($json['thirty_days_count'] ?? 0)]
                    : null;
            }
        }

        $skuProducts = $editorProducts === null
            ? $productIds
            : array_values(array_intersect($productIds, $editorProducts));

        try {
            $sku = ProductScopedSales::thirtyDaysFor($vendIds, $skuProducts);
        } catch (\Throwable $e) {
            // A broken figure must not take User edit down; the column stays blank.
            Log::warning('UserMachineVisibility: SKU L30d sales failed', ['error' => $e->getMessage()]);
            $sku = [];
        }

        return ['machine' => $machine, 'sku' => $sku];
    }

    /** Show (true) or No Show (false) one machine for this user. */
    public function set(User $user, int $vendId, bool $show, ?User $by = null): void
    {
        $before = $this->hiddenIds($user);

        if ($show) {
            DB::table('user_hidden_vends')->where('user_id', $user->id)->where('vend_id', $vendId)->delete();
        } else {
            DB::table('user_hidden_vends')->insertOrIgnore([
                'user_id' => $user->id,
                'vend_id' => $vendId,
                'created_by' => $by?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // A pivot write fires no model event, so the audit row is written by hand
        // (a synthetic column, as the Machine Stickers picker does).
        UserLogger::recordChanges($user, ['hidden_vend_ids' => [$before, $this->hiddenIds($user)]]);

        VendAccess::flush($user->id);
    }

    /**
     * When each product went into each machine (Brian, 2026-10-27: "when this SKU
     * start in the machine", not the Site's begin date) — the start of its CURRENT
     * stay: the first completed refill visit that carried it after the last visit
     * that did not. A visit lists every slot of the machine with its product
     * (ops_job_item_channels; prod 2026-10: 17–19 rows a visit, none partial), so a
     * visit without the product means it was out; a changeover visit lists the
     * arriving product (is_upcoming_product) and so counts as in. No visit since it
     * came back (a planogram edit with no refill yet) = no date.
     *
     * @param  array<int, int>  $vendIds
     * @param  array<int, int>  $productIds
     * @return array<int, array<string, string>> vend id => product code => Y-m-d
     */
    private function skuStartDates(array $vendIds, array $productIds): array
    {
        if ($vendIds === [] || $productIds === []) {
            return [];
        }

        // Completed visits that recorded the machine's slots, oldest first.
        $visits = DB::table('ops_job_items')
            ->whereIn('vend_id', $vendIds)
            ->whereNotNull('completed_at')
            ->whereNull('cancelled_at')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('ops_job_item_channels')->whereColumn('ops_job_item_channels.ops_job_item_id', 'ops_job_items.id'))
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get(['id', 'vend_id', 'completed_at'])
            ->groupBy('vend_id');

        // Which of those visits carried which of the products: by visit id on the
        // (product_id, ops_job_item_id) index — joining back to ops_job_items by
        // vend took 2.4 s for a 607-machine list on prod, this 0.9 s.
        $carried = collect($visits->flatten(1)->pluck('id')->chunk(5000))
            ->flatMap(fn ($chunk) => DB::table('ops_job_item_channels')
                ->whereIn('product_id', $productIds)
                ->whereIn('ops_job_item_id', $chunk->all())
                ->distinct()
                ->get(['ops_job_item_id as item_id', 'product_id']))
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('item_id')->map(fn ($id) => (int) $id)->flip());

        $codes = DB::table('products')->whereIn('id', $carried->keys()->all())->pluck('code', 'id');

        $starts = [];
        foreach ($visits as $vendId => $vendVisits) {
            foreach ($carried as $productId => $itemIds) {
                $start = null;
                foreach ($vendVisits as $visit) {
                    if ($itemIds->has((int) $visit->id)) {
                        $start ??= $visit->completed_at;
                    } else {
                        $start = null;
                    }
                }
                if ($start !== null && isset($codes[$productId])) {
                    $starts[(int) $vendId][$codes[$productId]] = Carbon::parse($start)->toDateString();
                }
            }
        }

        foreach ($starts as &$byCode) {
            ksort($byCode);
        }

        return $starts;
    }

    /** @return array<int, int> */
    private function hiddenIds(User $user): array
    {
        return DB::table('user_hidden_vends')->where('user_id', $user->id)
            ->pluck('vend_id')->map(fn ($id) => (int) $id)->all();
    }
}
