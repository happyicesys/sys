<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Applies a smart freezer's FREEZERCTLACK (see FreezerControlService::recordAck). */
class SyncFreezerControlAck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected array $input,
        protected Vend $vend,
    ) {}

    public function handle(FreezerControlService $service): void
    {
        $service->recordAck($this->vend, $this->input);
    }
}
