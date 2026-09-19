<?php

namespace App\Services\StockCheck\Sync;

use App\Models\StockCheckChannel;
use App\Models\User;
use App\Models\Vend;

/**
 * CityBox Smart Chillers: refused, on purpose.
 *
 * Their stock is theirs — mark1 re-reads it every poll, so a local write is
 * gone within minutes. The only real correction is their stocktake-submit
 * endpoint, which OVERWRITES the device's stock, needs a door-open msg_id, and
 * has never been confirmed safe with a partial product list (a spot check
 * counts a sample). Until CityBox confirms what happens to products left out
 * of the payload, sending a sample could zero the rest of the chiller.
 */
class CityboxSyncTarget implements StockCheckSyncTarget
{
    public function supports(Vend $vend): bool
    {
        return $vend->isSmartChiller();
    }

    public function refusal(Vend $vend): ?string
    {
        return 'A CityBox chiller\'s stock is corrected through a restock visit (Stock Adjustment), not from a spot check.';
    }

    public function notice(Vend $vend): string
    {
        return (string) $this->refusal($vend);
    }

    public function apply(StockCheckChannel $channel, User $by): ChannelSyncResult
    {
        return ChannelSyncResult::skipped($channel->vend_channel_code, (string) $this->refusal($channel->stockCheck->vend));
    }
}
