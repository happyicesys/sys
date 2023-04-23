<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'full_name' => $this->code.' - '.$this->name,
            'remarks' => $this->remarks,
            'desc' => $this->desc,
            'is_active' => $this->is_active ? true : false,
            'is_commission' => $this->is_commission ? true : false,
            'is_inventory' => $this->is_inventory ? true : false,
            'is_supermarket_fee' => $this->is_supermarket_fee ? true : false,
            'category_id' => CategoryResource::make($this->whenLoaded('category')),
            'category_group_id' => CategoryGroupResource::make($this->whenLoaded('categoryGroup')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'isActive' => $this->is_active ? 'Yes' : 'No',
            'isInventory' => $this->is_inventory ? 'Yes' : 'No',
            'thumbnail' => AttachmentResource::make($this->whenLoaded('thumbnail')),
            'latestUnitCost' => UnitCostResource::make($this->whenLoaded('latestUnitCost')),
            'unitCosts' => UnitCostResource::collection($this->whenLoaded('unitCosts')),
            'productUoms' => ProductUomResource::collection($this->whenLoaded('productUoms')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => OperatorResource::make($this->whenLoaded('operator')),
        ];
    }
}
