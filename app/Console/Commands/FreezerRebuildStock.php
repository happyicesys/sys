<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\OpsJob;
use App\Models\OpsJobItemChannel;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\Freezer\FreezerStockLedger;
use Illuminate\Console\Command;

/**
 * Recomputes a Smart Freezer's per-SKU stock: the last Stock In (the qty the page showed + the
 * refill) of that SKU, minus the units dispensed since. The live ledger is relative; this is the
 * one-off baseline for freezers stocked before it existed. For freezers stocked before
 * FreezerStockLedger existed (2026-09-30), and as a check on the live count. A SKU never stocked
 * in through an ops job is left alone. Dry run unless --apply.
 */
class FreezerRebuildStock extends Command
{
    protected $signature = 'freezer:rebuild-stock {vend : machine code} {--apply : write the result}';

    protected $description = 'Rebuild a Smart Freezer\'s stock from its last Stock In minus sales since';

    public function handle(FreezerStockLedger $ledger): int
    {
        $vend = Vend::withoutGlobalScopes()->where('code', $this->argument('vend'))
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)->first();
        if (! $vend) {
            $this->error('No Smart Freezer with that code.');

            return self::FAILURE;
        }

        $rows = [];
        $changed = 0;
        foreach (VendChannel::where('vend_id', $vend->id)->whereNotNull('product_id')->orderBy('code')->get() as $channel) {
            $line = OpsJobItemChannel::query()
                ->join('ops_job_items as oji', 'oji.id', '=', 'ops_job_item_channels.ops_job_item_id')
                ->where('ops_job_item_channels.vend_channel_id', $channel->id)
                ->where('oji.vend_id', $vend->id)
                ->where('oji.status', '>=', OpsJob::STATUS_DELIVERED)
                ->where('oji.status', '<>', OpsJob::STATUS_CANCELLED)
                ->whereNotNull('oji.completed_at')
                ->orderByDesc('oji.completed_at')
                ->first(['ops_job_item_channels.qty', 'ops_job_item_channels.actual_qty', 'oji.id as item_id', 'oji.completed_at']);

            if (! $line) {
                $rows[] = [$channel->code, $channel->product_id, $channel->qty, '—', '—', '—', 'no stock-in, left alone'];

                continue;
            }

            $stocked = max(0, (int) $line->qty + (int) $line->actual_qty);
            $sold = 0;
            VendTransaction::withoutGlobalScopes()
                ->where('vend_id', $vend->id)
                ->where('created_at', '>=', $line->completed_at)
                ->each(function (VendTransaction $sale) use ($ledger, $channel, &$sold) {
                    $sold += $ledger->unitsDispensed($sale)[(int) $channel->product_id] ?? 0;
                });

            $target = max(0, $stocked - $sold);
            $rows[] = [$channel->code, $channel->product_id, $channel->qty, "{$stocked} (item {$line->item_id})", $sold, $target, (int) $channel->qty === $target ? 'ok' : 'CHANGE'];

            if ((int) $channel->qty !== $target) {
                $changed++;
                if ($this->option('apply')) {
                    $channel->forceFill(['qty' => $target])->saveQuietly();
                }
            }
        }

        $this->table(['code', 'product', 'now', 'stocked in', 'sold since', 'should be', ''], $rows);

        if ($this->option('apply') && $changed > 0) {
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
            $this->info("Applied {$changed} change(s) to {$vend->code}.");
        } elseif ($changed > 0) {
            $this->warn("{$changed} change(s) — dry run, re-run with --apply.");
        }

        return self::SUCCESS;
    }
}
