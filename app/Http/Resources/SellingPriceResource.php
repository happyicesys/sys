<?php

namespace App\Http\Resources;

use App\Models\SellingPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellingPriceResource extends JsonResource
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
            'amount' => $this->amount,
            'product' => $this->whenLoaded('product'),
            'type' => $this->type,
            'type_name' => SellingPrice::TYPE_MAPPINGS[$this->type] ?? 'Unknown',
        ];
    }
}
