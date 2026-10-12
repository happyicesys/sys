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
 * Tells one machine to re-read the menu that carries its Happy Hour slots and welcome sketches, so a
 * slot change or a new or redrawn sketch reaches the screen without a reboot:
 *  - a smart freezer: `/menu`, via the same TYPESYNCAPICHANNELSLOTLIST nudge a mapping change sends
 *    (unchanged);
 *  - a big-board vending machine on app 315+ (HappyHourEligibility): the lightweight `HAPPYHOUR`
 *    frame, on which it re-reads `/thumbnails` JSON only — never the menu frame, which makes a vending
 *    board re-download every image. Any other machine gets nothing.
 * The planner and ProductWelcomeSketchService dispatch it delayed and debounced per machine, so a
 * batch of changes finishing together costs one fetch.
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
        if ($vend === null || ! $vend->is_active) {
            return;
        }
        if ($vend->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER) {
            $vendJobs->syncChannelSlotListToVend($vend);
        } elseif ($vend->machine_type === Vend::MACHINE_TYPE_VENDING_MACHINE) {
            $vendJobs->nudgeHappyHourToVend($vend);
        }
    }
}
