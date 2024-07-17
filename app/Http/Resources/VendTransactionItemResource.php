<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendTransactionItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_refunded' => $this->is_refunded,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'product_id' => $this->product_id,
            'unit_cost' => $this->unit_cost,
            'unitCost' => UnitCostResource::make($this->whenLoaded('unitCost')),
            'unit_cost_id' => $this->unit_cost_id,
            'vendChannel' => VendChannelResource::make($this->whenLoaded('vendChannel')),
            'vend_channel_id' => $this->vend_channel_id,
            'vend_channel_code' => $this->vend_channel_code,
            'vend_channel_error_code' => $this->vend_channel_error_code,
            'vendChannelError' => VendChannelErrorResource::make($this->whenLoaded('vendChannelError')),
            'vend_channel_error_id' => $this->vend_channel_error_id,
            'vendTransaction' => VendTransactionResource::make($this->whenLoaded('vendTransaction')),
            'vend_transaction_id' => $this->vend_transaction_id,
        ];
    }
}
