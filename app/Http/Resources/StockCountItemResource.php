<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCountItemResource extends JsonResource
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
            'stock_count_id' => $this->stock_count_id,
            'stock_cost_amount' => $this->stock_cost_amount,
            'stock_value_amount' => $this->stock_value_amount,
            'product_id' => $this->product_id,
            'qty_vend' => $this->qty_vend,
            'qty_warehouse' => $this->qty_warehouse,
            'unit_cost_amount' => $this->unit_cost_amount,
        ];
    }
}
