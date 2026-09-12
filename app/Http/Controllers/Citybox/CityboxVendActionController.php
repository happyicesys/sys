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
     * catalog for name/thumbnail.
     *
     * Plus `off_planogram`: SKUs their live stock reports that their restock
     * config does not carry, which therefore have no channel (see below).
     *
     * Opening the overview PULLS CityBox live first (Brian, 2026-09-10) — the
     * same refresh the Pull button runs: device status, their Pre-Stock config
     * (so par/prices/new SKUs land) and live stock. Never fatal: an offline
     * chiller, a disabled integration or an API blip still renders the last
     * synced planogram, with `refreshed:false` so the caption can say so.
     */
    public function planogram(int $id, CityboxOpenapiSync $sync, \App\Services\Citybox\StockPollService $poll): \Illuminate\Http\JsonResponse
    {
        $vend = $this->chillerOr403($id);

        $refreshError = null;
        try {
            $vend = $sync->pull($vend);
        } catch (\Throwable $e) {
            $refreshError = $e->getMessage();
            \Illuminate\Support\Facades\Log::info('Citybox overview: live refresh failed, serving last sync', [
                'vend_id' => $vend->id, 'error' => $refreshError,
            ]);
        }

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

        // ── Off-planogram: in the cabinet, absent from their Pre-Stock Setup ──
        // device_product can report a SKU their restock config does not carry
        // (C6005, 2026-09-12: five units sat on an off-sale duplicate SKU). Such
        // a SKU gets no channel and no par, so ops cannot refill it — but the
        // stock is physically inside and still sells, so it is GREYED IN here
        // instead of hidden. This is also why the cabinet total can read lower
        // than the device total CityBox itself reports.
        //
        // Their par config is the discriminator. When nothing is cached (TTL
        // gone AND the live pull failed) we claim nothing rather than invent
        // phantom rows out of every SKU.
        $parIds = $poll->cachedPlanogramCodes($vend);
        $offPlanogram = [];
        if ($parIds !== []) {
            $snapshot = is_array($status['stock'] ?? null) ? $status['stock'] : [];
            // Only SKUs actually HOLDING stock. A channel-less SKU at 0 says
            // nothing to ops — the whole point of the list is stock the cabinet
            // totals cannot see — and C6005 carries four such empty leftovers.
            $offRows = array_values(array_filter(
                $snapshot,
                fn ($r) => is_array($r)
                    && (int) ($r['quantity'] ?? 0) > 0
                    && ! isset($parIds[(int) ($r['product_id'] ?? 0)])
            ));
            $offCatalog = \App\Models\CityboxProduct::whereIn('citybox_product_id', array_map(fn ($r) => (int) ($r['product_id'] ?? 0), $offRows))
                ->with('product:id,code,name,is_active')->get()->keyBy('citybox_product_id');

            foreach ($offRows as $r) {
                $cbId = (int) ($r['product_id'] ?? 0);
                $cb = $offCatalog->get($cbId);
                $layer = isset($r['layer']) && is_numeric($r['layer']) ? (int) $r['layer'] : null;
                $offPlanogram[] = [
                    'citybox_product_id' => $cbId,
                    'layer' => $layer,
                    'qty' => (int) ($r['quantity'] ?? 0),
                    'amount_cents' => (int) ($r['active_price'] ?? $r['price'] ?? 0),
                    'citybox_name' => $r['name'] ?? $cb?->name,
                    'thumbnail' => $r['thumbnail'] ?? $cb?->img_url,
                    'product' => $cb?->product ? [
                        'id' => $cb->product->id, 'code' => $cb->product->code, 'name' => $cb->product->name,
                        'is_active' => (bool) $cb->product->is_active,
                    ] : null,
                    'mapped' => $cb?->product_id !== null,
                ];
            }
            usort($offPlanogram, fn ($a, $b) => [$a['layer'] ?? 99, $a['citybox_product_id']] <=> [$b['layer'] ?? 99, $b['citybox_product_id']]);
        }

        return response()->json([
            'vend' => ['id' => $vend->id, 'code' => $vend->code, 'equipment_id' => $vend->citybox_equipment_id],
            'citybox_name' => $status['name'] ?? null,
            'online' => (bool) ($status['online'] ?? $vend->is_online),
            'offline_since' => $status['heartbeat_last_offline'] ?? null,
            'device_type' => $status['device_type'] ?? null,
            'synced_at' => $vend->citybox_synced_at?->format('Y-m-d H:i'),
            'refreshed' => $refreshError === null,
            'refresh_error' => $refreshError,
            'layers' => array_values($layers), // layer 1 first (Brian, 2026-09-10) — matches their OPS Pro layer list
            'total_qty' => $channels->sum('qty'),
            'total_capacity' => $channels->sum('capacity'),
            'unmapped_count' => $channels->whereNull('product_id')->count(),
            // Channel totals above stay CityBox's par truth; these ride alongside.
            'off_planogram' => $offPlanogram,
            'off_planogram_qty' => array_sum(array_column($offPlanogram, 'qty')),
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
