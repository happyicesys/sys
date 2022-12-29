<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductMappingResource extends JsonResource
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
            'name' => $this->name,
            'remarks' => $this->remarks,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'productMappingItems' => ProductMappingItemResource::collection($this->whenLoaded('productMappingItems')),
            'productMappingItemsJson' => $this->product_mapping_items_json,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'vendsJson' => $this->vends_json,
        ];
    }
}
