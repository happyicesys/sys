<?php

namespace App\Services\Grab\Menu;

use App\Models\GrabMenuSyncStore;

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

        return GrabMenuSyncStore::query()->with('sync:id,summary,created_at')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (GrabMenuSyncStore $row) => [$row->delivery_product_mapping_vend_id => self::storeRow($row) + [
                'sync_summary' => $row->sync?->summary,
                'sync_at' => $row->sync?->created_at?->toIso8601String(),
            ]])
            ->all();
    }
}
