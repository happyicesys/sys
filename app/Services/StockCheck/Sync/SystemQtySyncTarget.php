<?php

namespace App\Services\StockCheck\Sync;

use App\Models\StockCheckChannel;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Vending machines and Smart Freezers: apply the variance to mark1's own
 * `vend_channels.qty`.
 *
 * The VARIANCE is applied, not the counted figure: if the machine sold two
 * since the count, "counted 5 against 8" still means three are missing, so the
 * right number now is current − 3, not 5.
 *
 * For a vending machine this corrects mark1 only. mark1 has no frame that sets
 * a VMC's channel qty, and the machine's next CHANNEL report overwrites
 * vend_channels.qty with its own figure — so the correction holds only if the
 * machine's count is corrected on the machine as well. A Smart Freezer sends
 * no CHANNEL frame; there vend_channels.qty is ours and the sync sticks.
 */
class SystemQtySyncTarget implements StockCheckSyncTarget
{
    public function supports(Vend $vend): bool
    {
        return ! $vend->isSmartChiller();
    }

    public function refusal(Vend $vend): ?string
    {
        return null;
    }

    public function notice(Vend $vend): string
    {
        return $vend->isSmartFreezer()
            ? 'The system quantity of each mismatched channel will be corrected by its variance.'
            : 'The system quantity of each mismatched channel will be corrected by its variance. '
                .'The machine keeps its own count: correct it on the machine too, or its next report will overwrite this.';
    }

    public function apply(StockCheckChannel $channel, User $by): ChannelSyncResult
    {
        return DB::transaction(function () use ($channel, $by) {
            // ---- validate before -------------------------------------------------
            $live = VendChannel::query()->lockForUpdate()->find($channel->vend_channel_id);

            if ($live === null) {
                return ChannelSyncResult::skipped($channel->vend_channel_code, 'Channel no longer exists on the machine.');
            }

            if ((int) $live->product_id !== (int) $channel->product_id) {
                return ChannelSyncResult::skipped($channel->vend_channel_code, 'The product in this channel changed after the count.');
            }

            $before = (int) $live->qty;
            $ceiling = max((int) $live->capacity, $before);
            $expected = min($ceiling, max(0, $before + (int) $channel->variance_qty));

            // ---- apply -----------------------------------------------------------
            $live->update(['qty' => $expected]);

            // ---- validate after --------------------------------------------------
            $after = (int) VendChannel::query()->whereKey($live->id)->value('qty');

            if ($after !== $expected) {
                throw new RuntimeException("Stock check sync: channel {$channel->vend_channel_code} read back {$after}, expected {$expected}.");
            }

            $channel->update([
                'synced_at' => now(),
                'synced_by' => $by->id,
                'qty_before_sync' => $before,
                'qty_after_sync' => $after,
            ]);

            $note = $expected !== $before + (int) $channel->variance_qty
                ? 'Clamped to the channel\'s 0…capacity range.'
                : null;

            return ChannelSyncResult::applied($channel->vend_channel_code, $before, $after, $note);
        });
    }
}
