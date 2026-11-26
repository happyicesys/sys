<?php

namespace Tests\Support;

use App\Jobs\Vend\SendMachineNudge;
use App\Services\VendJobService;
use Illuminate\Support\Facades\Queue;

/**
 * Fleet-wide frames are queued as paced SendMachineNudge jobs (MachineNudgePacer, 2026-11-26). A test that fakes
 * the queue runs them here, once each, to see the frames they send.
 */
trait RunsPacedNudges
{
    /** @var array<int, true> */
    private array $ranPacedNudges = [];

    protected function runPacedNudges(): void
    {
        foreach (Queue::pushed(SendMachineNudge::class) as $job) {
            if (isset($this->ranPacedNudges[spl_object_id($job)])) {
                continue;
            }
            $this->ranPacedNudges[spl_object_id($job)] = true;
            $job->handle(app(VendJobService::class));
        }
    }
}
