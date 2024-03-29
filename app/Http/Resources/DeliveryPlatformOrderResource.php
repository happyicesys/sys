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
            'campaign_json' => $this->campaign_json,
            'delivery_platform_id' => $this->delivery_platform_id,
            'deliveryPlatform' => DeliveryPlatformResource::make($this->whenLoaded('deliveryPlatform')),
            'delivery_platform_operator_id' => $this->delivery_platform_operator_id,
            'deliveryPlatformOrderComplaint' => DeliveryPlatformOrderComplaintResource::make($this->whenLoaded('deliveryPlatformOrderComplaint')),
            'deliveryPlatformOperator' => DeliveryPlatformOperatorResource::make($this->whenLoaded('deliveryPlatformOperator')),
            'deliveryPlatformOrderItems' => DeliveryPlatformOrderItemResource::collection($this->whenLoaded('deliveryPlatformOrderItems')),
            'delivery_product_mapping_vend_id' => $this->delivery_product_mapping_vend_id,
            'deliveryProductMappingVend' => DeliveryProductMappingVendResource::make($this->whenLoaded('deliveryProductMappingVend')),
            'driver_phone_number' => $this->driver_phone_number,
            'error_json' => $this->error_json,
            'is_cancelled' => $this->is_cancelled,
            'is_edited' => $this->is_edited,
            'is_verified' => $this->is_verified,
            'last_mile_timediff_mins' => $this->last_mile_timediff_mins,
            'order_created_at' => $this->order_created_at ?  Carbon::parse($this->order_created_at)->setTimezone($this->getUserTimezone())->format('ymd h:ia') : null,
            'order_id' => $this->order_id,
            'order_json' => $this->order_json,
            'orderItemVendChannels' => OrderItemVendChannelResource::collection($this->whenLoaded('orderItemVendChannels')),
            'platform_ref_id' => $this->platform_ref_id,
            'promo_amount' => $this->promo_amount,
            'request_history_json' => $this->request_history_json,
            'response_history_json' => $this->response_history_json,
            'short_order_id' => $this->short_order_id,
            'status' => $this->status,
            'status_name' => DeliveryPlatformOrder::STATUS_MAPPING[$this->status],
            'status_json' => $this->status_json,
            'subtotal_amount' => $this->subtotal_amount - $this->promo_amount,
            'total_amount' => $this->total_amount,
            'vendTransaction' => VendTransactionResource::make($this->whenLoaded('vendTransaction')),
            'vend_code' => $this->vend_code,
            'vend_id' => $this->vend_id,
            'vend_json' => $this->vend_json,
            'vend_transaction_order_id' => $this->vend_transaction_order_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'virtual_campaign_id_json' => $this->virtual_campaign_id_json,
        ];
    }
}
