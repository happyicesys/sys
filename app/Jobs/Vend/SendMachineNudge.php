<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Services\Vend\MachineNudgePacer;
use App\Services\VendJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** One machine's paced "re-read" frame (MachineNudgePacer). */
class SendMachineNudge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $vendId, public string $kind) {}

    public function handle(VendJobService $vendJobService): void
    {
        // Released first: a change made from here on queues the next frame instead of being swallowed.
        Cache::forget(MachineNudgePacer::pendingKey($this->vendId, $this->kind));

        $vend = Vend::withoutGlobalScopes()->find($this->vendId);
        if (! $vend) {
            return;
        }

        try {
            match ($this->kind) {
                MachineNudgePacer::MENU => $vendJobService->syncChannelSlotListToVend($vend),
                MachineNudgePacer::MENU_LIGHT => $vendJobService->nudgeHappyHourToVend($vend),
                MachineNudgePacer::SETTINGS => $vendJobService->syncSettingsToVend($vend),
                MachineNudgePacer::PAYMENT_MENU => $vendJobService->nudgePaymentMenuToVend($vend),
                MachineNudgePacer::MENU_AUTO => (new \App\Jobs\NudgeFreezerMenu($vend->id))->handle($vendJobService),
                MachineNudgePacer::PREPAID_VOUCHER => $vendJobService->nudgePrepaidVoucherToVend($vend),
                MachineNudgePacer::OTA_CHECK => app(\App\Services\OtaCheckNudge::class)->send($vend),
            };
        } catch (\Throwable $e) {
            Log::warning('SendMachineNudge failed', ['vend_code' => $vend->code, 'kind' => $this->kind, 'error' => $e->getMessage()]);
        }
    }
}
