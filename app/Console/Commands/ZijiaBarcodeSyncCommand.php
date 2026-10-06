<?php

namespace App\Console\Commands;

use App\Services\SmartFreezer\Zijia\ZijiaBarcodeSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Every 3 minutes: fills in missing freezer product barcodes from Zijia's SKU library, only on a
 * unique exact name match, then mirrors each barcoded freezer product's vms4 approval into mark1
 * (details + photos, refreshed when changed there) — ZijiaBarcodeSync / ZijiaLibraryImport.
 */
class ZijiaBarcodeSyncCommand extends Command
{
    protected $signature = 'smart-freezer:zijia-barcode-sync';

    protected $description = 'Fill missing smart-freezer product barcodes from Zijia\'s SKU library and mirror their vms4 approvals';

    public function handle(ZijiaBarcodeSync $sync): int
    {
        // One run at a time, scheduled or by hand: two overlapping runs import the same product
        // twice (prod, 2026-10-06 13:43 — the second insert hit the unique application number).
        $lock = Cache::lock('smart-freezer:zijia-barcode-sync', 600);
        if (! $lock->get()) {
            $this->warn('Another sync is running; skipped.');

            return self::SUCCESS;
        }
        try {
            return $this->sync($sync);
        } finally {
            $lock->release();
        }
    }

    private function sync(ZijiaBarcodeSync $sync): int
    {
        foreach ($sync->run() as $r) {
            $this->line(sprintf('%-6s %-45s barcode: %s', $r['product_id'], mb_substr($r['name'], 0, 45), $r['outcome']
                .(isset($r['barcode']) ? ' '.$r['barcode'] : '')
                .(isset($r['candidates']) ? ' ('.implode(', ', $r['candidates']).')' : '')));
        }
        // Then mirror what each barcoded freezer product was approved with in vms4 (photos too).
        foreach ($sync->importApproved() as $r) {
            if ($r['outcome'] !== 'unchanged') {
                $this->line(sprintf('%-6s %-45s vms4: %s', $r['product_id'], mb_substr($r['name'], 0, 45), $r['outcome']));
            }
        }

        return self::SUCCESS;
    }
}
