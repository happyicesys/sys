<?php

namespace App\Jobs\Screenshot;

use App\Models\Vend;
use App\Services\Screenshot\VendScreenshotRequester;
use App\Support\ScreenshotEligibility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One machine's 07:00 / 17:00 auto capture (`screenshots:capture-scheduled`). Dispatched delayed so
 * the fleet's uploads are spread out; the token is minted when this runs, not when it was queued, so
 * the delay never eats the machine's two minutes. Re-checks the machine at run time and steps aside
 * when an operator's own View Screen is still waiting for an answer.
 */
class RequestScheduledScreenshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(public readonly int $vendId)
    {
        $this->onQueue('default');
    }

    public function handle(VendScreenshotRequester $requester): void
    {
        $vend = Vend::withoutGlobalScopes()->find($this->vendId);
        if ($vend === null || ! $vend->is_active || $vend->is_disposed || ! $vend->is_online
            || ! ScreenshotEligibility::supports($vend) || $requester->isPending($vend)) {
            return;
        }

        if ($requester->request($vend, null) === VendScreenshotRequester::REQUESTED) {
            Log::info('Machine screen auto capture requested', ['vend_code' => $vend->code, 'vend_id' => $vend->id]);
        }
    }
}
