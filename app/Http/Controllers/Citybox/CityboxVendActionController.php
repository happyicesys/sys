<?php

namespace App\Http\Controllers\Citybox;

use App\Exceptions\CityboxApiException;
use App\Http\Controllers\Controller;
use App\Models\Vend;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\OpenapiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Per-vend CityBox actions on the Settings page. Every action is hard-gated
 * to machine_type = smart_chiller with a citybox_equipment_id — these
 * endpoints mean nothing for a vending machine or a smart freezer.
 */
class CityboxVendActionController extends Controller
{
    /**
     * Ops door-open (补货临时开门 / zyy_ls_open_door). NOT open_device — that
     * starts a consumer pay-later session and is off-limits to us. The
     * returned msg_id is what a later stocktake submit must reference.
     */
    public function openDoor(Request $request, int $id, OpenapiClient $client): RedirectResponse
    {
        $vend = $this->chillerOr403($id);

        try {
            $body = $client->zyyLsOpenDoor(
                $vend->citybox_equipment_id,
                // Attribute the open to a person in their logs.
                'mark1-u'.($request->user()?->id ?? 0),
            );
        } catch (CityboxApiException $e) {
            Log::warning('Citybox door-open failed', ['vend_id' => $vend->id, 'error' => $e->getMessage()]);

            return redirect()->back()->withErrors(['citybox' => 'Door open failed: '.$e->getMessage()]);
        }

        Log::info('Citybox door opened (ops)', [
            'vend_id' => $vend->id,
            'equipment_id' => $vend->citybox_equipment_id,
            'user_id' => $request->user()?->id,
            'msg_id' => $body['msg_id'] ?? null,
            'open_log_id' => $body['open_log_id'] ?? null,
        ]);

        // Keep the latest open handle on the vend so a stocktake submit can
        // pick it up without a new table for now.
        $status = $vend->citybox_status_json ?? [];
        $status['last_ops_open'] = [
            'msg_id' => $body['msg_id'] ?? null,
            'open_log_id' => $body['open_log_id'] ?? null,
            'user_id' => $request->user()?->id,
            'at' => now()->toDateTimeString(),
        ];
        $vend->forceFill(['citybox_status_json' => $status])->save();

        return redirect()->back()->with('success', 'Door opened (msg_id '.($body['msg_id'] ?? '?').').');
    }

    /** "Pull from Citybox" — refresh this vend's status + live stock now. */
    public function pull(int $id, CityboxOpenapiSync $sync): RedirectResponse
    {
        $vend = $this->chillerOr403($id);

        try {
            $sync->pull($vend);
        } catch (CityboxApiException $e) {
            return redirect()->back()->withErrors(['citybox' => 'Pull failed: '.$e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Pulled latest status and stock from Citybox.');
    }

    private function chillerOr403(int $id): Vend
    {
        $vend = Vend::findOrFail($id);
        abort_unless(
            $vend->machine_type === Vend::MACHINE_TYPE_SMART_CHILLER && $vend->citybox_equipment_id,
            403,
            'This action is only available for Smart Chiller (CityBox) vends with an equipment ID.'
        );

        return $vend;
    }
}
