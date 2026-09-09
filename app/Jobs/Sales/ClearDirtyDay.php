<?php

namespace App\Jobs\Sales;

use App\Services\Sales\DirtyDayRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tail of a rollup-rebuild chain: the day is only dropped from
 * DirtyDayRegistry once its three rebuilds have actually succeeded, so a
 * failed heal keeps its date for the next night.
 *
 * A real job, deliberately, not a queued closure. The tail used to be
 * `fn (string $d) => fn () => …clear($d)`, two arrow functions on one line;
 * laravel/serializable-closure reconstructs a closure by re-reading its source
 * line, picked up the OUTER one, and every tail died in the worker with
 * "Unable to resolve dependency [Parameter #0 [ <required> string $d ]]" while
 * the rebuilds themselves succeeded — so the dirty set never drained
 * (prod, 2026-09-09). A job carrying one string cannot fail that way.
 */
class ClearDirtyDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $day) {}

    public function handle(DirtyDayRegistry $registry): void
    {
        $registry->clear($this->day);
    }
}
