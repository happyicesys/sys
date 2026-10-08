<?php

namespace App\Jobs;

use App\Models\Vend;
use App\Services\VendJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells one smart freezer to re-read its `/menu` (the same TYPESYNCAPICHANNELSLOTLIST nudge a mapping
 * change sends), so a new or redrawn welcome sketch reaches the screen without a reboot.
 * ProductWelcomeSketchService dispatches it delayed and debounced per freezer, so a batch of sketches
 * finishing together costs the freezer one menu fetch.
 */
class NudgeFreezerMenu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $vendId)
    {
        $this->onQueue('low');
    }

    public function handle(VendJobService $vendJobs): void
    {
        $vend = Vend::withoutGlobalScopes()->find($this->vendId);
        if ($vend !== null && $vend->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER && $vend->is_active) {
            $vendJobs->syncChannelSlotListToVend($vend);
        }
    }
}
