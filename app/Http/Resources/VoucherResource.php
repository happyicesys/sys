<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
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
            'code' => $this->code,
            'created_at' => $this->created_at,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'desc' => $this->desc,
            'is_active' => $this->is_active,
            'is_batch_code' => $this->is_batch_code,
            'max_promo_value' => $this->max_promo_value,
            'max_redemption_count' => $this->max_redemption_count,
            'min_value' => $this->min_value,
            'name' => $this->name,
            'product_json' => $this->product_json,
            'qty' => $this->qty,
            'response_json' => $this->response_json,
            'status' => $this->status,
            'value' => $this->value,
            'vend_id' => $this->vend_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
