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

    /**
     * JSON for SmartChillerChannelOverview.vue: 5 layers → channels from
     * vend_channels (qty/capacity/amount) joined to the mirror mapping + CityBox
     * catalog for name/thumbnail. Reads only what the poller/planogram wrote.
     */
    public function planogram(int $id): \Illuminate\Http\JsonResponse
    {
        $vend = $this->chillerOr403($id);
        $status = $vend->citybox_status_json ?? [];

        // Channel rows are the truth for qty/capacity/amount/product; layer = hundreds digit of the code (101…699).
        $channels = $vend->vendChannels()->where('is_active', true)->with('product:id,code,name,is_active')->orderBy('code')->get();
        // CityBox name/thumbnail per channel: match by product via the catalog, else by the snapshot's layer/order.
        $catalog = \App\Models\CityboxProduct::whereIn('product_id', $channels->pluck('product_id')->filter())->get()->keyBy('product_id');

        $layers = [];
        foreach (range(1, 5) as $l) {
            $layers[$l] = ['layer' => $l, 'channels' => [], 'qty' => 0, 'capacity' => 0];
        }
        foreach ($channels as $ch) {
            $layer = \App\Services\Citybox\ChillerPlanogram::layerOf((int) $ch->code);
            if ($layer < 1 || $layer > 5) {
                continue;
            }
            $cb = $ch->product_id ? $catalog->get($ch->product_id) : null;
            $layers[$layer]['channels'][] = [
                'code' => (int) $ch->code,
                'qty' => (int) $ch->qty,
                'capacity' => (int) $ch->capacity,
                'amount_cents' => (int) $ch->amount,
                'product' => $ch->product ? [
                    'id' => $ch->product->id, 'code' => $ch->product->code, 'name' => $ch->product->name,
                    // CityBox disabled the SKU: the channel is greyed out, never dropped (Brian, 2026-09-05).
                    'is_active' => (bool) $ch->product->is_active,
                ] : null,
                'citybox_name' => $cb?->name,
                'thumbnail' => $cb?->img_url,
                'mapped' => $ch->product_id !== null,
            ];
            $layers[$layer]['qty'] += (int) $ch->qty;
            $layers[$layer]['capacity'] += (int) $ch->capacity;
        }

        return response()->json([
            'vend' => ['id' => $vend->id, 'code' => $vend->code, 'equipment_id' => $vend->citybox_equipment_id],
            'citybox_name' => $status['name'] ?? null,
            'online' => (bool) ($status['online'] ?? $vend->is_online),
            'offline_since' => $status['heartbeat_last_offline'] ?? null,
            'device_type' => $status['device_type'] ?? null,
            'synced_at' => $vend->citybox_synced_at?->format('Y-m-d H:i'),
            'layers' => array_values(array_reverse($layers)), // layer 5 first = top of the rack
            'total_qty' => $channels->sum('qty'),
            'total_capacity' => $channels->sum('capacity'),
            'unmapped_count' => $channels->whereNull('product_id')->count(),
        ]);
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
