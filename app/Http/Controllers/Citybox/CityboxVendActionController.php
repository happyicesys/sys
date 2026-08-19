<?php

namespace App\Http\Controllers\Citybox;

use App\Exceptions\CityboxApiException;
use App\Http\Controllers\Controller;
use App\Models\Vend;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\RestockVisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-vend CityBox actions on the Settings page. Thin: gate, delegate,
 * flash. Hard-gated to machine_type = smart_chiller with an equipment id.
 */
class CityboxVendActionController extends Controller
{
    public function openDoor(Request $request, int $id, RestockVisitService $visits): RedirectResponse
    {
        $vend = $this->chillerOr403($id);

        try {
            $session = $visits->openDoor($vend, $request->user(), 'vend_settings');
        } catch (CityboxApiException $e) {
            return redirect()->back()->withErrors(['citybox' => 'Door open failed: '.$e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Door opened (msg_id '.$session->msgId.').');
    }

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
