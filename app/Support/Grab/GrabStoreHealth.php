<?php

namespace App\Support\Grab;

use App\Models\Customer;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Is a machine's Grab store running? One rule for the Operation Dashboard's Grab badge (green / red) and its
 * "Grab Store" filter (Brian, 2026-11-19). An OPEN binding (no `end_date`) is a PROBLEM when any of:
 *
 * - paused (`is_active` off, by a person, "Pause until", or GrabSiteGuard after a Site change);
 * - its store is switched off (ref number Inactive);
 * - its machine is on no Site;
 * - its machine is at another Site than the one the store was set up at (`customer_id`, kept while Grab may still
 *   show the old address — see DeliveryProductMappingVend::siteChangedFrom).
 *
 * Otherwise it is SELLING. A binding with no Site on record (set up before the column was kept) is judged on the
 * first three only. `problemSql()` and `forVends()` must say the same thing; GrabStoreHealthTest pins it.
 */
class GrabStoreHealth
{
    const SELLING = 'selling';

    const PROBLEM = 'problem';

    /**
     * SQL condition on an open binding aliased `b` (joined ref number `r`) and its machine `v`: true = problem.
     */
    public static function problemSql(string $b = 'b', string $r = 'r', string $v = 'v'): string
    {
        $inactive = DeliveryPlatformRefNumber::STATUS_INACTIVE;

        return "({$b}.is_active = 0"
            ." OR COALESCE({$r}.status, 0) = {$inactive}"
            ." OR {$v}.customer_id IS NULL"
            ." OR ({$b}.customer_id IS NOT NULL AND {$b}.customer_id <> {$v}.customer_id))";
    }

    /**
     * WHERE EXISTS for a query that has the `vends` table: the machine carries an open Grab binding in $state
     * (selling = every open binding of it is fine, problem = at least one is not).
     */
    public static function applyFilter($query, string $state, string $vendsTable = 'vends'): void
    {
        $open = fn ($sub) => $sub->selectRaw('1')
            ->from('delivery_product_mapping_vend as gsh_b')
            ->leftJoin('delivery_platform_ref_numbers as gsh_r', 'gsh_r.id', '=', 'gsh_b.delivery_platform_ref_number_id')
            ->whereColumn('gsh_b.vend_id', $vendsTable.'.id')
            ->whereNull('gsh_b.end_date');
        $problem = self::problemSql('gsh_b', 'gsh_r', $vendsTable);

        if ($state === self::PROBLEM) {
            $query->whereExists(fn ($sub) => $open($sub)->whereRaw($problem));
        } elseif ($state === self::SELLING) {
            $query->whereExists(fn ($sub) => $open($sub))
                ->whereNotExists(fn ($sub) => $open($sub)->whereRaw($problem));
        } elseif ($state === 'none') {
            $query->whereNotExists(fn ($sub) => $open($sub));
        }
    }

    /**
     * Open Grab bindings of these machines, for the badge: vend_id => list of
     * {platform, merchant_id, store_name, state, reasons[]}. One query (plus Site names for moved ones).
     */
    public static function forVends($vendIds): Collection
    {
        $vendIds = collect($vendIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($vendIds->isEmpty()) {
            return collect();
        }

        $rows = DB::table('delivery_product_mapping_vend as b')
            ->join('vends as v', 'v.id', '=', 'b.vend_id')
            ->leftJoin('delivery_platform_ref_numbers as r', 'r.id', '=', 'b.delivery_platform_ref_number_id')
            ->leftJoin('delivery_product_mappings as m', 'm.id', '=', 'b.delivery_product_mapping_id')
            ->leftJoin('delivery_platform_operators as o', 'o.id', '=', 'm.delivery_platform_operator_id')
            ->leftJoin('delivery_platforms as p', 'p.id', '=', 'o.delivery_platform_id')
            ->whereIn('b.vend_id', $vendIds->all())
            ->whereNull('b.end_date')
            ->orderBy('b.id')
            ->get([
                'b.id', 'b.vend_id', 'b.is_active', 'b.paused_until', 'b.pause_reason', 'b.customer_id as store_site_id',
                'b.platform_ref_id', 'v.customer_id as machine_site_id', 'r.ref_number', 'r.status as ref_status',
                'r.grab_store_name', 'p.name as platform',
            ]);

        $siteIds = $rows->filter(fn ($row) => $row->store_site_id && (int) $row->store_site_id !== (int) $row->machine_site_id)
            ->pluck('store_site_id')->unique()->values();
        $sites = $siteIds->isEmpty() ? collect() : Customer::query()->withoutGlobalScopes()
            ->whereIn('id', $siteIds->all())
            ->get(['id', 'code', 'name', 'virtual_customer_prefix', 'virtual_customer_code'])
            ->keyBy('id');

        return $rows->groupBy('vend_id')->map(fn ($bindings) => $bindings->map(function ($row) use ($sites) {
            $reasons = self::reasons($row, $sites);

            return [
                'id' => (int) $row->id,
                'platform' => $row->platform ?: 'Grab',
                'merchant_id' => $row->ref_number ?: $row->platform_ref_id,
                'store_name' => $row->grab_store_name,
                'state' => $reasons ? self::PROBLEM : self::SELLING,
                'reasons' => $reasons,
            ];
        })->values());
    }

    private static function reasons(object $row, Collection $sites): array
    {
        $reasons = [];
        if (! $row->is_active) {
            $line = __('Paused');
            if ($row->paused_until) {
                $line .= ' '.__('until :time', ['time' => Carbon::parse($row->paused_until)->format('d M H:i')]);
            }
            if ($row->pause_reason) {
                $line .= ': '.$row->pause_reason;
            }
            $reasons[] = $line;
        }
        if ((int) $row->ref_status === DeliveryPlatformRefNumber::STATUS_INACTIVE) {
            $reasons[] = __('Store switched off (Inactive)');
        }
        if (! $row->machine_site_id) {
            $reasons[] = __('Machine is not on any Site');
        } elseif ($row->store_site_id && (int) $row->store_site_id !== (int) $row->machine_site_id) {
            $reasons[] = __('Machine left Site :site, which Grab may still show', [
                'site' => DeliveryProductMappingVend::siteLabel($sites->get((int) $row->store_site_id)) ?? '#'.$row->store_site_id,
            ]);
        }

        return $reasons;
    }
}
