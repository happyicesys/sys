<?php

namespace App\Http\Resources;

use App\Models\DeliveryPlatformRefNumber;
use App\Http\Resources\DeliveryProductMappingVendResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformRefNumberResource extends JsonResource
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
            'delivery_platform_id' => $this->delivery_platform_id,
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'binding_count' => $this->when(isset($this->delivery_product_mapping_vends_count), function() {
                return (int) $this->delivery_product_mapping_vends_count;
            }, 0),
            'current_vend_code' => $this->whenLoaded('currentDeliveryProductMappingVend', function() {
                return $this->currentDeliveryProductMappingVend?->vend_code;
            }, null),
            'ref_number' => $this->ref_number,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'status_label' => isset(DeliveryPlatformRefNumber::STATUS_MAPPINGS[$this->status]) ? DeliveryPlatformRefNumber::STATUS_MAPPINGS[$this->status] : 'Unknown',
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y H:i A') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d/m/Y H:i A') : null,
            'delivery_product_mapping_vends' => DeliveryProductMappingVendResource::collection($this->whenLoaded('deliveryProductMappingVends')),
        ];
    }
}
