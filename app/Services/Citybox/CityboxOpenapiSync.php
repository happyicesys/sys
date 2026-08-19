<?php

namespace App\Services\Citybox;

use App\Models\Vend;

/**
 * Facade over the two single-responsibility halves (DeviceSyncService =
 * status, StockPollService = stock). Kept so the poll job and the vend
 * "Pull" action have one entry point; contains NO logic of its own.
 */
class CityboxOpenapiSync
{
    public function __construct(
        private DeviceSyncService $devices,
        private StockPollService $stock,
    ) {}

    public static function hasLinkedVends(): bool
    {
        return DeviceSyncService::hasLinkedVends();
    }

    /**
     * @return array{equipment:int,matched:int,missing:array,unknown:array,stock_errors:array}
     */
    public function syncAll(): array
    {
        $fleet = $this->devices->syncFleet();
        $stockErrors = $this->stock->pollAll($fleet['vends'], $fleet['devices']);

        return [
            'equipment' => $fleet['devices']->count(),
            'matched' => $fleet['matched'],
            'missing' => $fleet['missing'],
            'unknown' => $fleet['unknown'],
            'stock_errors' => $stockErrors,
        ];
    }

    /** One-vend refresh for the "Pull from Citybox" action. */
    public function pull(Vend $vend): Vend
    {
        $this->devices->refreshOne($vend);
        $this->stock->pollOne($vend);

        return $vend->refresh();
    }
}
