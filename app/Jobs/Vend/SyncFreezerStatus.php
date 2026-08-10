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
 * Handles the smart freezer's `FREEZERSTATUS` (900 s serviceability snapshot) and `SELFCHK` (daily
 * diagnostic) packets. Both land in `vends.freezer_status_json` under their own key.
 *
 * This is the *serviceable* axis of machine health — `is_online` only tells us the box is reachable,
 * not that its driver board, locks and cameras are alive.
 */
class SyncFreezerStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected array $input,
        protected Vend $vend,
        protected string $type = 'FREEZERSTATUS',
    ) {}

    public function handle(FreezerStatusService $service): void
    {
        if ($this->type === 'SELFCHK') {
            $service->syncSelfCheck($this->vend, $this->input);

            return;
        }

        $service->syncStatus($this->vend, $this->input);
    }
}
