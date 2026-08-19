<?php

namespace App\Observers;

use App\Jobs\SubmitCityboxCount;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Vend;
use Illuminate\Support\Facades\Log;

/**
 * When a SMART CHILLER item moves to Stocked-In, queue the CityBox stock
 * submit (design §6c.5). Same discipline as OpsJobItemObserver: early-return
 * unless the status actually changed, and never let a CityBox failure break a
 * driver's stock-in save — everything is try/caught and queued.
 */
class CityboxOpsJobItemObserver
{
    public function saved(OpsJobItem $item): void
    {
        if (! $item->wasChanged('status') || (string) $item->status !== (string) OpsJob::STATUS_DELIVERED) {
            return;
        }
        if (! config('citybox.openapi.enabled')) {
            return;
        }

        try {
            $vend = $item->vend ?? Vend::withoutGlobalScopes()->find($item->vend_id);
            if (! $vend || $vend->machine_type !== Vend::MACHINE_TYPE_SMART_CHILLER || ! $vend->citybox_equipment_id) {
                return;
            }
            $item->forceFill(['citybox_submit_status' => 'pending', 'citybox_submit_error' => null])->saveQuietly();
            // 5 s: after the controller's channel loop has committed actual_qty.
            SubmitCityboxCount::dispatch($item->id)->delay(now()->addSeconds(5))->onQueue('high');
        } catch (\Throwable $e) {
            Log::warning('CityboxOpsJobItemObserver: could not queue count submit for ops_job_item '.$item->id.': '.$e->getMessage());
        }
    }
}
