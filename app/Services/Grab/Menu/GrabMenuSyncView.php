<?php

namespace App\Services\Grab\Menu;

use App\Models\GrabMenuSyncStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// What Grab Menu Sync and Grab Menu & Prices' "On Grab" column show of a store's sync row.
final class GrabMenuSyncView
{
    // Problems first, then what is still moving, then done.
    const ORDER = [
        GrabMenuSyncStore::STATUS_FAILED => 0,
        GrabMenuSyncStore::STATUS_TURNED_AWAY => 1,
        GrabMenuSyncStore::STATUS_WAITING => 2,
        GrabMenuSyncStore::STATUS_SENT => 3,
        GrabMenuSyncStore::STATUS_PULLED => 4,
        GrabMenuSyncStore::STATUS_SKIPPED => 5,
        GrabMenuSyncStore::STATUS_SYNCED => 6,
    ];

    public static function storeRow(GrabMenuSyncStore $row): array
    {
        return [
            'id' => $row->id,
            'binding_id' => $row->delivery_product_mapping_vend_id,
            'vend_code' => $row->vend_code,
            'merchant_id' => $row->merchant_id,
            'status' => $row->status,
            'attempts' => $row->attempts,
            'retry_at' => $row->retry_at?->toIso8601String(),
            'last_code' => $row->last_code,
            'last_message' => $row->last_message,
            'sent_at' => $row->sent_at?->toIso8601String(),
            'pulled_at' => $row->pulled_at?->toIso8601String(),
            'synced_at' => $row->synced_at?->toIso8601String(),
            'last_checked_at' => $row->last_checked_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Grab Menu & Prices' "On Grab" column: each store's latest change, as one line. Polled while any store is
     * still moving.
     *
     * @param  int[]  $bindingIds
     * @return array<int, array> by binding id
     */
    public static function latestByBinding(array $bindingIds): array
    {
        if (! $bindingIds) {
            return [];
        }
        $ids = GrabMenuSyncStore::query()->whereIn('delivery_product_mapping_vend_id', $bindingIds)
            ->groupBy('delivery_product_mapping_vend_id')->selectRaw('MAX(id) AS id')->pluck('id');

        return GrabMenuSyncStore::query()->with(['sync:id,summary,created_at,user_id,kind', 'sync.user:id,name'])->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (GrabMenuSyncStore $row) => [$row->delivery_product_mapping_vend_id => self::storeRow($row) + [
                'sync_summary' => $row->sync?->summary,
                'sync_at' => $row->sync?->created_at?->toIso8601String(),
                // Who made the change: a person's name, else NULL with the kind (nightly sync, the system's check).
                'sync_by' => $row->sync?->user?->name,
                'sync_kind' => $row->sync?->kind,
            ]])
            ->all();
    }

    /**
     * Who caused each store's last item / price change on Grab (Brian, 2026-11-21: "last updated at and by who"). The
     * change is seen when Grab takes the menu, so its cause is the store's latest recorded change sent before that,
     * within a day. None = not known (the change came another way, e.g. a product mapping edit).
     *
     * @param  array<int, array{items: ?string, prices: ?string}>  $changes  ISO times by binding id
     * @return array<int, array{items: ?array, prices: ?array}> by binding id: {by: ?name, kind}
     */
    public static function causesByBinding(array $changes): array
    {
        $times = collect($changes)->flatMap(fn ($pair) => array_filter($pair))->map(fn ($at) => Carbon::parse($at));
        if ($times->isEmpty()) {
            return [];
        }
        $sent = DB::table('grab_menu_sync_stores as store')
            ->join('grab_menu_syncs as sync', 'sync.id', '=', 'store.grab_menu_sync_id')
            ->leftJoin('users', 'users.id', '=', 'sync.user_id')
            ->whereIn('store.delivery_product_mapping_vend_id', array_keys($changes))
            ->where('sync.created_at', '>=', $times->min()->copy()->subDay())
            ->where('sync.created_at', '<=', $times->max())
            ->orderByDesc('sync.created_at')->orderByDesc('sync.id')
            ->get(['store.delivery_product_mapping_vend_id as binding_id', 'sync.created_at', 'sync.kind', 'users.name'])
            ->groupBy('binding_id');

        $cause = function ($rows, ?string $at): ?array {
            if (! $at || ! $rows) {
                return null;
            }
            $at = Carbon::parse($at);
            $row = $rows->first(fn ($row) => Carbon::parse($row->created_at)->between($at->copy()->subDay(), $at));

            return $row ? ['by' => $row->name, 'kind' => $row->kind] : null;
        };

        return collect($changes)->map(fn ($pair, $bindingId) => [
            'items' => $cause($sent[$bindingId] ?? null, $pair['items'] ?? null),
            'prices' => $cause($sent[$bindingId] ?? null, $pair['prices'] ?? null),
        ])->all();
    }
}
