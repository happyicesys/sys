<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\User;
use App\Models\Vend;
use App\Services\Citybox\DTO\RestockSession;
use Illuminate\Support\Facades\Log;

/**
 * The ops visit against a chiller: door-open now; count-submit + B/A frames
 * arrive in later build steps. Every door-open in mark1 — Settings page,
 * ops-job page, ops-item page — goes through openDoor(), so logging,
 * attribution and the msg_id hand-off happen in exactly one place.
 *
 * (Step 6 replaces the `last_ops_open` JSON stopgap with
 * ops_job_items.citybox_msg_id + citybox_door_open_logs; the method
 * signature stays.)
 */
class RestockVisitService
{
    public function __construct(private ChillerGateway $gateway) {}

    /** @throws \App\Exceptions\CityboxApiException on refusal (offline, busy, unknown) */
    public function openDoor(Vend $vend, ?User $by, string $source = 'vend_settings'): RestockSession
    {
        $session = $this->gateway->openForRestock(
            (string) $vend->citybox_equipment_id,
            'mark1-u'.($by?->id ?? 0),
        );

        Log::info('Citybox door opened (ops)', [
            'vend_id' => $vend->id,
            'equipment_id' => $vend->citybox_equipment_id,
            'user_id' => $by?->id,
            'source' => $source,
            'msg_id' => $session->msgId,
            'open_log_id' => $session->openLogId,
        ]);

        // Stopgap home for the latest open handle until step 6's log table
        // + ops_job_items.citybox_msg_id land.
        $status = $vend->citybox_status_json ?? [];
        $status['last_ops_open'] = [
            'msg_id' => $session->msgId,
            'open_log_id' => $session->openLogId,
            'user_id' => $by?->id,
            'source' => $source,
            'at' => $session->openedAt->toDateTimeString(),
        ];
        $vend->forceFill(['citybox_status_json' => $status])->save();

        return $session;
    }
}
