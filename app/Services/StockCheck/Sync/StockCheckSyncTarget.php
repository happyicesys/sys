<?php

namespace App\Services\StockCheck\Sync;

use App\Models\StockCheckChannel;
use App\Models\User;
use App\Models\Vend;

/**
 * How a counted variance is applied to one kind of machine. mark1 owns the
 * number for some machines and only mirrors it for others, so "sync" cannot be
 * one code path — each machine kind answers for itself.
 */
interface StockCheckSyncTarget
{
    public function supports(Vend $vend): bool;

    /** Why this machine kind cannot be synced; null when it can. */
    public function refusal(Vend $vend): ?string;

    /** Shown to the person before they confirm — what the sync will and will not do. */
    public function notice(Vend $vend): string;

    public function apply(StockCheckChannel $channel, User $by): ChannelSyncResult;
}
