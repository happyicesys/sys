<?php

namespace App\Console\Commands;

use App\Models\OpsJobItem;
use App\Support\OpsJobFrameQty;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Refill `ops_job_item_channels.vmc_before_qty` / `vmc_after_qty` from the B/A
 * frames already stored on the linked `vend_channel_records` row.
 *
 * THE BUG. SyncVendChannels::frameEntryMatchesOpsRow (added 2026-09-22 with the
 * SKU-stocked freezer/chiller work, deployed 2026-09-23) keyed the match on
 * "does the frame entry carry a product_id?". A vending board's B/A frame does
 * carry one — but it is the VMC's own slot index (1, 2, 21 …), never a
 * products.id — so it was compared against ops_job_item_channels.product_id
 * (506, 563 …) and matched nothing.
 *
 * WHY ONLY SOME ITEMS. The completion path in OpsJobController matches on
 * channel_code and was never broken, so it fills both columns from whatever
 * JSON is on the record at the moment the driver presses Complete. A driver who
 * closes the door and waits for the A frame is unaffected. A driver who presses
 * Complete first leaves the A frame to arrive afterwards, and that path runs
 * through the broken helper — After Refill stays blank for the whole item. On
 * 2026-09-23 that was 6 of 15 items, all of them one driver's, and zero on every
 * day before the deploy.
 *
 * THE REPAIR. Replay the stored frames with the corrected rule: product_id only
 * on a SKU-stocked machine (Vend::isSkuStocked), slot code otherwise. The frames
 * are kept verbatim on vend_channel_records, so this is an exact replay, not a
 * reconstruction. Rows whose code appears in no frame entry are left alone.
 *
 * Dry run by default; pass --apply to write.
 */
class RepairOpsJobItemVmcQty extends Command
{
    protected $signature = 'ops-job:repair-vmc-qty
                            {--from= : Only items completed on or after this date (default: 2026-09-23, the deploy day)}
                            {--to= : Only items completed on or before this date}
                            {--item= : Repair a single ops_job_item id}
                            {--apply : Write the values; without it the command only reports}';

    protected $description = 'Refill ops-job Before/After Refill qty from the stored B/A frames';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = OpsJobItem::query()
            ->whereNotNull('vend_channel_record_id')
            ->with(['vendChannelRecord', 'opsJobItemChannels', 'vend']);

        if ($itemId = $this->option('item')) {
            $query->where('id', $itemId);
        } else {
            $from = Carbon::parse($this->option('from') ?: '2026-09-23')->startOfDay();
            $query->where('completed_at', '>=', $from);

            if ($to = $this->option('to')) {
                $query->where('completed_at', '<=', Carbon::parse($to)->endOfDay());
            }
        }

        $itemsTouched = 0;
        $rowsBefore = 0;
        $rowsAfter = 0;

        $query->orderBy('id')->chunkById(200, function ($items) use ($apply, &$itemsTouched, &$rowsBefore, &$rowsAfter) {
            foreach ($items as $item) {
                $record = $item->vendChannelRecord;

                if (! $record) {
                    continue;
                }

                // Count first: a dry run reports exactly what --apply would write.
                $itemBefore = $this->fillable($item, $record->before_data_json, 'vmc_before_qty');
                $itemAfter = $this->fillable($item, $record->after_data_json, 'vmc_after_qty');

                if ($apply) {
                    OpsJobFrameQty::apply($item, $record->before_data_json, 'vmc_before_qty', onlyWhenNull: true);
                    OpsJobFrameQty::apply($item, $record->after_data_json, 'vmc_after_qty', onlyWhenNull: true);
                }

                if ($itemBefore || $itemAfter) {
                    $itemsTouched++;
                    $rowsBefore += $itemBefore;
                    $rowsAfter += $itemAfter;

                    $this->line(sprintf(
                        'item %d (vend %d, completed %s): before +%d, after +%d',
                        $item->id,
                        $item->vend_id,
                        $item->completed_at,
                        $itemBefore,
                        $itemAfter
                    ));
                }
            }
        });

        $this->info(sprintf(
            '%s %d item(s): %d before-qty and %d after-qty row(s).',
            $apply ? 'Repaired' : 'Would repair',
            $itemsTouched,
            $rowsBefore,
            $rowsAfter
        ));

        if (! $apply && $itemsTouched) {
            $this->comment('Dry run — re-run with --apply to write.');
        }

        return self::SUCCESS;
    }

    /** How many of an item's rows this frame could still fill. */
    private function fillable(OpsJobItem $item, ?array $frame, string $column): int
    {
        if (! $frame) {
            return 0;
        }

        $skuStocked = OpsJobFrameQty::isSkuStocked($item);
        $count = 0;

        foreach ($item->opsJobItemChannels as $opsRow) {
            if ($opsRow->{$column} === null && OpsJobFrameQty::qtyFor($frame, $opsRow, $skuStocked) !== null) {
                $count++;
            }
        }

        return $count;
    }
}
