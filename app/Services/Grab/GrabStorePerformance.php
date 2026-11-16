<?php

namespace App\Services\Grab;

use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Support\VendCode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grab Performance (Brian, 2026-11-16): how each Grab store sells, to decide which machines stay on
 * Grab and which move. One rule with Grab Stores' sales columns: a store's sales are its machine's NOW
 * (the open link), delivered orders only, less Grab promo, integer cents. A period is whole calendar
 * days ending today; the previous period is the same number of days just before it.
 */
class GrabStorePerformance
{
    public const PERIODS = [7, 14, 30, 60, 90];

    public const DEFAULT_PERIOD = 30;

    /** A store sells much less than the period before: below this share of it. */
    private const DROP_SHARE = 0.5;

    /**
     * @param  int[]  $operatorIds  already narrowed to what the viewer may see
     */
    public function report(array $operatorIds, int $days, string $codes = ''): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
        $from = Carbon::today()->subDays($days - 1);
        $previousFrom = $from->copy()->subDays($days);
        $until = Carbon::tomorrow();

        $refs = DeliveryPlatformRefNumber::query()
            ->whereIn('operator_id', $operatorIds)
            ->when($codes !== '', fn ($query) => $query->whereExists(function ($binding) use ($codes) {
                $binding->selectRaw('1')->from('delivery_product_mapping_vend as b')
                    ->join('vends as v', 'v.id', '=', 'b.vend_id')
                    ->whereColumn('b.delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                    ->whereNull('b.end_date');
                VendCode::whereSearch($binding, $codes, contains: true, table: 'v');
            }))
            ->with([
                'operator' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name'),
                'openDeliveryProductMappingVends' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'vend:id,code,code_prefix,name,customer_id',
                    'vend.customer' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name', 'virtual_customer_prefix', 'virtual_customer_code'),
                    'customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                ]),
            ])
            ->get(['id', 'ref_number', 'operator_id', 'status', 'grab_store_name']);

        $daily = $this->dailyByStore($refs->pluck('id')->all(), $previousFrom, $until);
        $lastOrders = $this->lastOrderByStore($refs->pluck('id')->all());
        $dates = collect(range(0, $days - 1))->map(fn ($i) => $from->copy()->addDays($i)->toDateString())->all();

        $stores = $refs
            ->map(fn (DeliveryPlatformRefNumber $ref) => $this->storeRow($ref, $daily[$ref->id] ?? [], $dates, $from, $previousFrom, $days, $lastOrders[$ref->id] ?? null))
            // A store with no machine and no sale in either period says nothing about performance.
            ->filter(fn ($row) => $row['binding'] || $row['orders'] || $row['previous_orders'])
            ->sortByDesc('sales')
            ->values();

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => Carbon::today()->toDateString(),
            'previous_from' => $previousFrom->toDateString(),
            'previous_to' => $from->copy()->subDay()->toDateString(),
            'summary' => $this->summary($stores),
            'daily' => $this->dailyTotals($stores, $dates),
            'stores' => $stores->all(),
        ];
    }

    /** @return array<int, array<string, array{sales: int, orders: int}>> store id => date => figures */
    private function dailyByStore(array $refIds, Carbon $from, Carbon $until): array
    {
        if (! $refIds) {
            return [];
        }

        $out = [];
        DB::table('delivery_platform_orders as o')
            ->join('delivery_product_mapping_vend as ob', 'ob.id', '=', 'o.delivery_product_mapping_vend_id')
            ->whereIn('ob.delivery_platform_ref_number_id', $refIds)
            ->whereNull('ob.end_date')
            ->where('o.status', DeliveryPlatformOrder::STATUS_DELIVERED)
            ->where('o.order_created_at', '>=', $from)
            ->where('o.order_created_at', '<', $until)
            ->groupBy('ob.delivery_platform_ref_number_id', DB::raw('DATE(o.order_created_at)'))
            ->selectRaw('ob.delivery_platform_ref_number_id AS ref_id, DATE(o.order_created_at) AS day')
            ->selectRaw('COALESCE(SUM(o.subtotal_amount - o.promo_amount), 0) AS sales, COUNT(*) AS orders')
            ->get()
            ->each(function ($row) use (&$out) {
                $out[(int) $row->ref_id][(string) $row->day] = ['sales' => (int) $row->sales, 'orders' => (int) $row->orders];
            });

        return $out;
    }

    /** @return array<int, string> store id => its machine's latest delivered Grab order (any time) */
    private function lastOrderByStore(array $refIds): array
    {
        if (! $refIds) {
            return [];
        }

        return DB::table('delivery_platform_orders as o')
            ->join('delivery_product_mapping_vend as ob', 'ob.id', '=', 'o.delivery_product_mapping_vend_id')
            ->whereIn('ob.delivery_platform_ref_number_id', $refIds)
            ->whereNull('ob.end_date')
            ->where('o.status', DeliveryPlatformOrder::STATUS_DELIVERED)
            ->groupBy('ob.delivery_platform_ref_number_id')
            ->selectRaw('ob.delivery_platform_ref_number_id AS ref_id, MAX(o.order_created_at) AS last_at')
            ->pluck('last_at', 'ref_id')
            ->map(fn ($at) => Carbon::parse($at)->toIso8601String())
            ->all();
    }

    private function storeRow(DeliveryPlatformRefNumber $ref, array $byDay, array $dates, Carbon $from, Carbon $previousFrom, int $days, ?string $lastOrderAt): array
    {
        /** @var DeliveryProductMappingVend|null $binding */
        $binding = $ref->openDeliveryProductMappingVends->first();
        $status = $ref->sellingStatus();

        $daily = array_map(fn ($date) => ['date' => $date] + ($byDay[$date] ?? ['sales' => 0, 'orders' => 0]), $dates);
        $sales = array_sum(array_column($daily, 'sales'));
        $orders = array_sum(array_column($daily, 'orders'));
        $previousSales = 0;
        $previousOrders = 0;
        foreach ($byDay as $date => $figures) {
            if ($date < $from->toDateString()) {
                $previousSales += $figures['sales'];
                $previousOrders += $figures['orders'];
            }
        }

        // Days the machine has been on this store: a young one is not read as a slow one.
        $since = $binding?->start_date;
        $sinceDay = $since?->copy()->startOfDay();
        $daysOnMachine = $sinceDay ? (int) $sinceDay->diffInDays(Carbon::today()) + 1 : null;
        // A link with no start date (none on prod, 2026-11-16) counts as an old one.
        $coversPeriod = ! $sinceDay || $sinceDay->lte($from);
        $coversPrevious = ! $sinceDay || $sinceDay->lte($previousFrom);

        $flag = null;
        if ($status === 'selling') {
            if (! $coversPeriod) {
                $flag = 'new';
            } elseif ($orders === 0) {
                $flag = 'no_orders';
            } elseif ($coversPrevious && $previousSales > 0 && $sales < $previousSales * self::DROP_SHARE) {
                $flag = 'dropping';
            }
        }

        return [
            'id' => $ref->id,
            'ref_number' => $ref->ref_number,
            'name' => $ref->grab_store_name,
            'operator' => $ref->operator?->code,
            'status' => $status,
            'binding' => $binding ? [
                'vend_code' => $binding->vend_code,
                'vend' => $binding->vend?->only(['id', 'code', 'code_prefix', 'name']),
                'site' => $binding->siteName(),
                'site_id' => $binding->currentSite()?->id,
                'since' => $since?->toIso8601String(),
                'days_on_machine' => $daysOnMachine,
            ] : null,
            'sales' => $sales,
            'orders' => $orders,
            'average_order' => $orders ? intdiv($sales + intdiv($orders, 2), $orders) : null,
            'days_with_orders' => count(array_filter($daily, fn ($day) => $day['orders'] > 0)),
            'previous_sales' => $previousSales,
            'previous_orders' => $previousOrders,
            // Only against a period the machine was on the store for all of.
            'change_pct' => $coversPrevious && $previousSales > 0 ? round(100 * ($sales - $previousSales) / $previousSales, 1) : null,
            'last_order_at' => $lastOrderAt,
            'flag' => $flag,
            'daily' => $daily,
        ];
    }

    private function summary(Collection $stores): array
    {
        $sales = (int) $stores->sum('sales');
        $orders = (int) $stores->sum('orders');
        $previousSales = (int) $stores->sum('previous_sales');
        $previousOrders = (int) $stores->sum('previous_orders');
        $selling = $stores->where('status', 'selling');

        return [
            'sales' => $sales,
            'orders' => $orders,
            'average_order' => $orders ? intdiv($sales + intdiv($orders, 2), $orders) : null,
            'previous_sales' => $previousSales,
            'previous_orders' => $previousOrders,
            'sales_change_pct' => $previousSales > 0 ? round(100 * ($sales - $previousSales) / $previousSales, 1) : null,
            'orders_change_pct' => $previousOrders > 0 ? round(100 * ($orders - $previousOrders) / $previousOrders, 1) : null,
            'stores_selling' => $selling->count(),
            // Of the selling stores, shown under that count.
            'stores_with_orders' => $selling->where('orders', '>', 0)->count(),
            'stores_no_orders' => $stores->where('flag', 'no_orders')->count(),
            'stores_dropping' => $stores->where('flag', 'dropping')->count(),
        ];
    }

    private function dailyTotals(Collection $stores, array $dates): array
    {
        $totals = array_fill_keys($dates, ['sales' => 0, 'orders' => 0]);
        foreach ($stores as $store) {
            foreach ($store['daily'] as $day) {
                $totals[$day['date']]['sales'] += $day['sales'];
                $totals[$day['date']]['orders'] += $day['orders'];
            }
        }

        return collect($totals)->map(fn ($figures, $date) => ['date' => $date] + $figures)->values()->all();
    }
}
