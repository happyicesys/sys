<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCheckChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $live = $this->relationLoaded('vendChannel') ? $this->vendChannel : null;
        $product = $this->relationLoaded('product') ? $this->product : null;

        return [
            'id' => $this->id,
            'vend_channel_code' => $this->vend_channel_code,
            'product_id' => $this->product_id,
            'product_code' => $product?->code,
            'product_name' => $product?->name,
            'product_thumbnail_url' => $product && $product->relationLoaded('thumbnail') ? $product->thumbnail?->full_url : null,
            // A mapping swap after the draw: the driver is looking at another product now.
            'is_product_changed' => $live !== null && (int) $live->product_id !== (int) $this->product_id,
            'capacity' => $this->capacity,
            'amount' => $this->amount, // cents
            // While pending the driver sees the live figure; once counted, the frozen one.
            'current_qty' => $this->isCounted() ? $this->system_qty : $live?->qty,
            'max_qty' => max((int) $this->capacity, (int) ($live?->qty ?? 0)),
            'system_qty' => $this->system_qty,
            'counted_qty' => $this->counted_qty,
            'variance_qty' => $this->variance_qty,
            'variance_value' => $this->varianceValueCents(), // cents
            'note' => $this->note,
            'is_synced' => $this->isSynced(),
            'synced_at' => $this->synced_at?->format('ymd h:i a'),
            'qty_before_sync' => $this->qty_before_sync,
            'qty_after_sync' => $this->qty_after_sync,
        ];
    }
}
