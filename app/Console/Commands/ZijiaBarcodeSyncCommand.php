<?php

namespace App\Console\Commands;

use App\Services\SmartFreezer\Zijia\ZijiaBarcodeSync;
use Illuminate\Console\Command;

/**
 * Every 3 minutes: fills in missing freezer product barcodes from Zijia's SKU library, only on a
 * unique exact name match (ZijiaBarcodeSync). Until Zijia offers an approval callback, this is how
 * a product approved in their portal starts being recognised.
 */
class ZijiaBarcodeSyncCommand extends Command
{
    protected $signature = 'smart-freezer:zijia-barcode-sync';

    protected $description = 'Fill missing smart-freezer product barcodes from Zijia\'s SKU library (unique exact name match only)';

    public function handle(ZijiaBarcodeSync $sync): int
    {
        foreach ($sync->run() as $r) {
            $this->line(sprintf('%-6s %-45s %s', $r['product_id'], mb_substr($r['name'], 0, 45), $r['outcome']
                .(isset($r['barcode']) ? ' '.$r['barcode'] : '')
                .(isset($r['candidates']) ? ' ('.implode(', ', $r['candidates']).')' : '')));
        }

        return self::SUCCESS;
    }
}
