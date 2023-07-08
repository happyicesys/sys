<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendTransactionGraphResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'amount' => $this->amount/ 100,
            'count' => $this->count,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'date' => $this->date,
            'day' => $this->day,
            'month' => $this->month,
            'month_name' => $this->month_name,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'product_id' => isset($this->product_id) ? $this->product_id : null,
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
