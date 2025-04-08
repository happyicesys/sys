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
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'desc' => $this->desc,
            'is_active' => $this->is_active,
            'is_batch_code' => $this->is_batch_code,
            'is_redeemed' => $this->is_redeemed,
            'max_promo_value' => $this->max_promo_value,
            'max_redemption_count' => $this->max_redemption_count,
            'member_id' => $this->member_id,
            'min_value' => $this->min_value,
            'name' => $this->name,
            'product_json' => $this->product_json,
            'qty' => $this->qty,
            'redeemed_at' => $this->redeemed_at,
            'response_json' => $this->response_json,
            'status' => $this->status,
            'vend_id' => $this->vend_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'created_at' => $this->created_at,
        ];
    }
}
