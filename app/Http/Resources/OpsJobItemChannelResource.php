<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobItemChannelResource extends JsonResource
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
            'actual_before_qty' => $this->actual_before_qty,
            'actual_qty' => $this->actual_qty,
            'capacity' => $this->capacity,
            'error_settled_at' => $this->error_settled_at,
            'error_settled_at_formatted' => $this->error_settled_at ? $this->error_settled_at->format('ymd h:i a (D)') : null,
            'is_error_settle' => $this->is_error_settle,
            'is_manually_replaced' => $this->is_manually_replaced,
            'is_upcoming_product' => $this->is_upcoming_product,
            'replaces_ops_job_item_channel_id' => $this->replaces_ops_job_item_channel_id,
            'ops_job_id' => $this->ops_job_id,
            'amount' => $this->amount,
            'ops_job_item_id' => $this->ops_job_item_id,
            'picked_before_qty' => $this->picked_before_qty,
            'picked_qty' => $this->picked_qty,
            'product_id' => $this->product_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'qty' => $this->qty,
            'saved_picked_qty' => $this->saved_picked_qty,
            'vendChannel' => VendChannelResource::make($this->whenLoaded('vendChannel')),
            'vend_channel_id' => $this->vend_channel_id,
            'vend_channel_code' => $this->vend_channel_code,
            'vend_code' => $this->vend_code,
            'vmc_before_qty' => $this->vmc_before_qty,
            'vmc_after_qty' => $this->vmc_after_qty,
            'virtual_is_error' => $this->virtual_is_error,
        ];
    }
}
