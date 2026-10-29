<?php

namespace App\Console\Commands;

use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only health of the Grab integration over the last N days: the baseline each
 * phase of GRAB_REVAMP_PLAN_2026-10-29.md is checked against. Writes nothing.
 */
class GrabHealth extends Command
{
    protected $signature = 'grab:health {--days=7 : Window in days}';

    protected $description = 'Report Grab orders, refused calls, menu syncs, paused / moved stores and held stock';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $this->info("Grab health, last {$days} day(s) (since {$since->toDateTimeString()})");

        $this->orders($since);
        $this->refusedOrderCalls($since);
        $this->menuSyncs($since);
        $this->stores();
        $this->heldStock();
        $this->deliveredNotDispensed($since);

        return self::SUCCESS;
    }

    private function orders($since): void
    {
        $rows = DB::table('delivery_platform_orders as o')
            ->leftJoin('delivery_platform_operators as dpo', 'dpo.id', '=', 'o.delivery_platform_operator_id')
            ->leftJoin('operators as op', 'op.id', '=', 'dpo.operator_id')
            ->where('o.created_at', '>=', $since)
            ->groupBy('op.code', 'dpo.type')
            ->orderBy('op.code')
            ->get([
                DB::raw("COALESCE(op.code, '?') as operator"),
                'dpo.type',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(o.dispensed_at IS NOT NULL) as dispensed'),
                DB::raw('SUM(o.is_cancelled = 1 OR o.status = '.DeliveryPlatformOrder::STATUS_CANCELLED.') as cancelled'),
                DB::raw('SUM(o.status = '.DeliveryPlatformOrder::STATUS_DELIVERED.' AND o.dispensed_at IS NULL) as delivered_not_dispensed'),
                DB::raw('SUM(o.is_edited = 1) as edited'),
            ]);

        $this->newLine();
        $this->line('<comment>Orders</comment>');
        $this->table(['operator', 'type', 'orders', 'dispensed', 'cancelled', 'delivered, not dispensed', 'edited by Grab'],
            $rows->map(fn ($r) => (array) $r)->all());
    }

    // Grab's order calls (auth probe, one row per call, retries included) against orders stored.
    private function refusedOrderCalls($since): void
    {
        $calls = DB::table('delivery_platform_auth_probes')
            ->where('route', 'api/delivery/grab/order/create')
            ->where('created_at', '>=', $since)
            ->whereNotNull('merchant_id')
            ->groupBy('merchant_id')
            ->selectRaw('merchant_id, COUNT(*) AS n')
            ->pluck('n', 'merchant_id');

        $saved = DB::table('delivery_platform_orders')
            ->where('created_at', '>=', $since)
            ->whereIn('platform_ref_id', $calls->keys())
            ->groupBy('platform_ref_id')
            ->selectRaw('platform_ref_id, COUNT(*) AS n')
            ->pluck('n', 'platform_ref_id');

        $rows = $calls->map(fn ($n, $merchant) => [$merchant, $n, (int) ($saved[$merchant] ?? 0)])
            ->filter(fn ($r) => $r[1] > $r[2])
            ->sortByDesc(fn ($r) => $r[1] - $r[2])
            ->values()->all();

        $this->newLine();
        $this->line('<comment>Order calls not stored (calls > orders; a call that failed is retried by Grab)</comment>');
        $rows ? $this->table(['merchant', 'calls', 'orders stored'], $rows) : $this->line('  none');
    }

    private function menuSyncs($since): void
    {
        $status = "JSON_UNQUOTE(JSON_EXTRACT(request_json, '$.status'))";

        $byStatus = DB::table('delivery_platform_menu_records')
            ->where('created_at', '>=', $since)
            ->groupBy(DB::raw($status))
            ->get([DB::raw("{$status} as status"), DB::raw('COUNT(*) as n'), DB::raw('COUNT(DISTINCT platform_ref_id) as stores')]);

        $failed = DB::table('delivery_platform_menu_records')
            ->where('created_at', '>=', $since)
            ->whereRaw("{$status} = 'FAILED'")
            ->groupBy('platform_ref_id', 'vend_code')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(15)
            ->get(['platform_ref_id', 'vend_code', DB::raw('COUNT(*) as n'), DB::raw("MAX(JSON_UNQUOTE(JSON_EXTRACT(request_json, '$.errors[0]'))) as error")]);

        $this->newLine();
        $this->line('<comment>Menu sync callbacks</comment>');
        $this->table(['status', 'callbacks', 'stores'], $byStatus->map(fn ($r) => (array) $r)->all());

        if ($failed->isNotEmpty()) {
            $this->table(['merchant', 'Grab partnerMerchantID', 'failed', 'error'], $failed->map(fn ($r) => [
                $r->platform_ref_id, $r->vend_code, $r->n, mb_strimwidth((string) $r->error, 0, 80, '…'),
            ])->all());
        }
    }

    // Open rows, and stores whose machine differs from the partnerMerchantID Grab sends.
    private function stores(): void
    {
        $open = DB::table('delivery_product_mapping_vend as v')
            ->leftJoin('delivery_platform_ref_numbers as rn', 'rn.id', '=', 'v.delivery_platform_ref_number_id')
            ->whereNull('v.end_date')
            ->get(['v.id', 'v.vend_code', 'v.is_active', DB::raw('COALESCE(rn.ref_number, v.platform_ref_id) as merchant_id'),
                DB::raw('(rn.status <=> '.DeliveryPlatformRefNumber::STATUS_INACTIVE.') as ref_inactive')]);

        $grabCode = DB::table('delivery_platform_menu_records')
            ->whereIn('platform_ref_id', $open->pluck('merchant_id')->filter())
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('vend_code')
            ->groupBy('platform_ref_id')
            ->selectRaw('platform_ref_id, MAX(vend_code) AS vend_code')
            ->pluck('vend_code', 'platform_ref_id');

        $selling = $open->filter(fn ($v) => $v->is_active && ! $v->ref_inactive);
        $moved = $selling->filter(fn ($v) => isset($grabCode[$v->merchant_id]) && (string) $grabCode[$v->merchant_id] !== (string) $v->vend_code);

        $this->newLine();
        $this->line('<comment>Stores</comment>');
        $this->line(sprintf('  bound: %d   selling: %d   paused: %d   ref Inactive: %d', $open->count(), $selling->count(),
            $open->where('is_active', 0)->count(), $open->filter(fn ($v) => $v->ref_inactive)->count()));

        if ($moved->isNotEmpty()) {
            $this->line('  Bound to another machine than the partnerMerchantID Grab sends (served by merchant id since 2026-10-29):');
            $this->table(['mapping_vend', 'merchant', 'bound vend', 'Grab sends'], $moved->map(fn ($v) => [
                $v->id, $v->merchant_id, $v->vend_code, $grabCode[$v->merchant_id],
            ])->values()->all());
        }
    }

    private function heldStock(): void
    {
        $counter = (int) DB::table('delivery_product_mapping_vend_channels as c')
            ->join('delivery_product_mapping_vend as v', 'v.id', '=', 'c.delivery_product_mapping_vend_id')
            ->whereNull('v.end_date')
            ->sum('c.order_qty');

        $held = (int) DB::table('order_item_vend_channels as oivc')
            ->join('delivery_platform_orders as o', 'o.id', '=', 'oivc.delivery_platform_order_id')
            ->where('o.created_at', '>=', now()->subHours(DeliveryPlatformOrder::ORDER_EXPIRED_HOURS))
            ->where('o.is_cancelled', false)
            ->whereNull('o.dispensed_at')
            ->whereNotIn('o.status', DeliveryPlatformOrder::STATUSES_NOT_HOLDING_STOCK)
            ->sum('oivc.qty');

        $this->newLine();
        $this->line('<comment>Stock held for Grab orders</comment>');
        $this->line("  units on open orders (what the menu subtracts): {$held}");
        $this->line("  legacy order_qty counter (no longer read): {$counter}");
    }

    private function deliveredNotDispensed($since): void
    {
        $rows = DB::table('delivery_platform_orders')
            ->where('created_at', '>=', $since)
            ->where('status', DeliveryPlatformOrder::STATUS_DELIVERED)
            ->whereNull('dispensed_at')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'short_order_id', 'vend_code', 'platform_ref_id', 'created_at', 'driver_phone_number']);

        $this->newLine();
        $this->line('<comment>Delivered by Grab, never dispensed by the machine</comment>');
        $rows->isNotEmpty()
            ? $this->table(['id', 'order', 'vend', 'merchant', 'created', 'driver keyed in?'], $rows->map(fn ($r) => [
                $r->id, $r->short_order_id, $r->vend_code, $r->platform_ref_id, $r->created_at, $r->driver_phone_number ? 'yes' : 'no',
            ])->all())
            : $this->line('  none');
    }
}
