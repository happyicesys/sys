<?php

namespace App\Http\Resources;

use App\Models\DeliveryPlatformOrder;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformOrderResource extends JsonResource
{
    use GetUserTimezone;
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
            'deliveryPlatform' => DeliveryPlatformResource::make($this->whenLoaded('deliveryPlatform')),
            'delivery_platform_operator_id' => $this->delivery_platform_operator_id,
            'deliveryPlatformOrderComplaint' => DeliveryPlatformOrderComplaintResource::make($this->whenLoaded('deliveryPlatformOrderComplaint')),
            'deliveryPlatformOperator' => DeliveryPlatformOperatorResource::make($this->whenLoaded('deliveryPlatformOperator')),
            'deliveryPlatformOrderItems' => DeliveryPlatformOrderItemResource::collection($this->whenLoaded('deliveryPlatformOrderItems')),
            'delivery_product_mapping_vend_id' => $this->delivery_product_mapping_vend_id,
            'deliveryProductMappingVend' => DeliveryProductMappingVendResource::make($this->whenLoaded('deliveryProductMappingVend')),
            'driver_arrived_at' => $this->driver_arrived_at,
            'driver_assigned_at' => $this->driver_assigned_at,
            'driver_eta_seconds' => $this->driver_eta_seconds,
            'driver_eta_updated_at' => $this->driver_eta_updated_at,
            'driver_phone_number' => $this->driver_phone_number,
            'error_json' => $this->error_json,
            'is_cancelled' => $this->is_cancelled,
            'is_edited' => $this->is_edited,
            'order_completed_at' => $this->order_completed_at,
            'order_created_at' => $this->order_created_at ?  Carbon::parse($this->order_created_at)->setTimezone($this->getUserTimezone())->toDatetimeString() : null,
            'ref_id' => $this->ref_id,
            'order_id' => $this->order_id,
            'order_json' => $this->order_json,
            'orderItemVendChannels' => OrderItemVendChannelResource::collection($this->whenLoaded('orderItemVendChannels')),
            'platform_ref_id' => $this->platform_ref_id,
            'request_history_json' => $this->request_history_json,
            'response_history_json' => $this->response_history_json,
            'short_order_id' => $this->short_order_id,
            'status' => $this->status,
            'status_name' => DeliveryPlatformOrder::STATUS_MAPPING[$this->status],
            'subtotal_amount' => $this->subtotal_amount,
            'total_amount' => $this->total_amount,
            'vend_code' => $this->vend_code,
            'vend_id' => $this->vend_id,
            'vend_transaction_order_id' => $this->vend_transaction_order_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
