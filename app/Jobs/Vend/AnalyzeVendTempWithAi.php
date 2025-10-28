<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendTemp;
use App\Services\VendTempAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeVendTempWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $vendId,
        public ?int $latestTempId = null,
        public array $snapshot = []
    ) {
    }

    public function handle(VendTempAiService $service): void
    {
        if (!$service->isEnabled()) {
            return;
        }

        $vend = Vend::find($this->vendId);

        if (!$vend) {
            return;
        }

        $latestTemp = $this->latestTempId ? VendTemp::find($this->latestTempId) : null;
        $result = $service->analyze($vend, $this->snapshot, $latestTemp);

        if (!$result) {
            return;
        }

        Log::info('Vend temperature AI decision', [
            'vend_id' => $vend->id,
            'decision' => $result['decision'],
        ]);
    }
}
