<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherItemResource extends JsonResource
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
            'member_id' => $this->member_id,
            'is_active' => $this->is_active,
            'is_redeemed' => $this->is_redeemed,
            'redeemed_at' => $this->redeemed_at,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'voucher_id' => $this->voucher_id,
            'voucher' => VoucherResource::make($this->whenLoaded('voucher')),
        ];
    }
}
