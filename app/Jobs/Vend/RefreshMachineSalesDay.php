<?php

namespace App\Jobs\Vend;

use App\Jobs\StoreVendsRecord;
use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One machine's closed day changed after the nightly rollup: rebuild its vend_records row, then its
 * dashboard totals, in that order, since the totals read the row. Unique only until it starts, so a
 * second correction made during a run still gets a run of its own.
 */
class RefreshMachineSalesDay implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 2;

    public $timeout = 120;

    public $uniqueFor = 300;

    public function __construct(public int $vendId, public string $day) {}

    public function uniqueId(): string
    {
        return $this->vendId.'_'.$this->day;
    }

    public function handle(): void
    {
        (new StoreVendsRecord($this->day, $this->day, false, $this->vendId))->handle();

        if ($vend = Vend::withoutGlobalScopes()->find($this->vendId)) {
            SyncVendTransactionTotalsJson::dispatchSync($vend);
        }
    }
}
