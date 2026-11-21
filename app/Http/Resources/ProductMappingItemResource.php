<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductMappingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'channel_code' => $this->channel_code,
            // The job page's "implement new mapping" diff for a freezer / chiller matches by product.
            'product_id' => $this->product_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
            'selling_price_id' => $this->selling_price_id,
            'sellingPrice' => SellingPriceResource::make($this->whenLoaded('sellingPrice')),
            'sequence' => $this->sequence,
            'capacity_override' => $this->capacity_override,
            'server_amount' => isset($this->server_amount) && $this->server_amount != null ? $this->server_amount : null,
        ];
    }
}
