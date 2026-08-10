<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Services\Freezer\FreezerStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Handles `POWERCUT` / `POWERRESTORED` from the smart freezer's reserve battery.
 *
 * Why this matters: today an outage and a SIM failure are indistinguishable — `SyncOnlineStatus` can
 * only *infer* "power restored" when a machine returns within 15 minutes. An explicit signal splits
 * `offline_power` (site problem, don't roll a truck) from `offline_unknown` (device problem), and
 * catches the case that is invisible today: mains lost but the battery holding, so the freezer never
 * goes offline at all while its spoilage clock is running.
 *
 * Queued on `high`: the packet is a last gasp sent on battery and its ordering guard depends on
 * being applied promptly relative to the reconnect flood behind it.
 */
class SyncFreezerPowerEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected array $input,
        protected Vend $vend,
        protected bool $isRestored = false,
    ) {}

    public function handle(FreezerStatusService $service): void
    {
        if ($this->isRestored) {
            $service->recordPowerRestored($this->vend, $this->input);

            return;
        }

        $service->recordPowerCut($this->vend, $this->input);
    }
}
