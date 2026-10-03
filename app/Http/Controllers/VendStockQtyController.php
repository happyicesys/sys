<?php

namespace App\Http\Controllers;

use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelQtyAdjustment;
use App\Services\Stock\ChannelQtyAdjuster;
use App\Services\Stock\QtyAdjustRefused;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Setting/Edit "Stock Qty": list a SKU-stocked machine's on-hand qty with the last hand
 * overwrite of each row, and apply a new one. The rules live in ChannelQtyAdjuster; this is
 * gate, delegate, answer. `Vend::findOrFail` keeps the operator scope (a viewer can only reach
 * machines they can see).
 */
class VendStockQtyController extends Controller
{
    /** How many past overwrites each row carries for its history list. */
    private const HISTORY = 5;

    public function index(int $id, ChannelQtyAdjuster $adjuster): JsonResponse
    {
        $vend = Vend::findOrFail($id);

        $channels = VendChannel::where('vend_id', $vend->id)
            ->where('is_active', true)
            ->whereNotNull('product_id')
            ->with('product:id,code,name')
            ->orderBy('code')->orderBy('suffix')
            ->get(['id', 'vend_id', 'code', 'suffix', 'product_id', 'qty', 'capacity']);

        $history = VendChannelQtyAdjustment::whereIn('vend_channel_id', $channels->pluck('id'))
            ->with('user:id,name')
            ->orderByDesc('id')
            ->get()
            ->groupBy('vend_channel_id')
            ->map(fn ($rows) => $rows->take(self::HISTORY)->map(fn (VendChannelQtyAdjustment $a) => $this->entry($a))->values());

        return response()->json([
            'refusal' => $adjuster->refusal($vend),
            'is_chiller' => $vend->isSmartChiller(),
            'channels' => $channels->map(fn (VendChannel $c) => [
                'id' => $c->id,
                'code' => (string) $c->code,
                'label' => $c->label,
                'product' => $c->product ? ['id' => $c->product->id, 'code' => $c->product->code, 'name' => $c->product->name] : null,
                'qty' => (int) $c->qty,
                'capacity' => (int) $c->capacity,
                'history' => $history->get($c->id, collect())->all(),
            ])->values(),
        ]);
    }

    public function update(Request $request, int $id, int $channelId, ChannelQtyAdjuster $adjuster): JsonResponse
    {
        $vend = Vend::findOrFail($id);
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:'.ChannelQtyAdjuster::MAX_QTY],
            'expected_qty' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $adjustment = $adjuster->adjust($vend, $channelId, (int) $data['qty'], (int) $data['expected_qty'], $request->user());
        } catch (QtyAdjustRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['adjustment' => $this->entry($adjustment->load('user:id,name'))]);
    }

    private function entry(VendChannelQtyAdjustment $a): array
    {
        return [
            'who' => $a->user?->name,
            'at' => $a->created_at?->toIso8601String(),
            'from' => $a->qty_before,
            'to' => $a->qty_after,
            'supplier_to' => $a->supplier_qty_after,
        ];
    }
}
