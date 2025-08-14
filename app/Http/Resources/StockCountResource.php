<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCountResource extends JsonResource
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
            'cash_sales_amount' => $this->cash_sales_amount,
            'cashless_sales_amount' => $this->cashless_sales_amount,
            'coin_float_amount' => $this->coin_float_amount,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'customer_id' => $this->customer_id,
            'day' => $this->day,
            'location_type_id' => $this->location_type_id,
            'month' => $this->month,
            'operator_id' => $this->operator_id,
            'product_mapping_id' => $this->product_mapping_id,
            'vend_id' => $this->vend_id,
            'vend_contract_id' => $this->vend_contract_id,
            'vend_model_id' => $this->vend_model_id,
            'vend_prefix_id' => $this->vend_prefix_id,
            'year' => $this->year,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'locationType' => new LocationTypeResource($this->whenLoaded('locationType')),
            'operator' => new OperatorResource($this->whenLoaded('operator')),
            'productMapping' => new ProductMappingResource($this->whenLoaded('productMapping')),
            'vend' => new VendResource($this->whenLoaded('vend')),
            'vendContract' => new VendContractResource($this->whenLoaded('vendContract')),
            'vendModel' => new VendModelResource($this->whenLoaded('vendModel')),
            'vendPrefix' => new VendPrefixResource($this->whenLoaded('vendPrefix')),
        ];
    }
}
