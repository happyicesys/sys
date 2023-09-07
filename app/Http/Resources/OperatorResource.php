<?php

namespace App\Http\Resources;

use DateTimeZone;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorResource extends JsonResource
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
            'deliveryPlatformOperators' => DeliveryPlatformOperatorResource::collection($this->whenLoaded('deliveryPlatformOperators')),
            'name' => $this->name,
            'gst_vat_rate' => $this->gst_vat_rate,
            'full_name' => $this->code.' - '.$this->name,
            'remarks' => $this->remarks,
            'timezone' => [
                'id' => array_search($this->timezone, DateTimeZone::listIdentifiers()),
                'name' => $this->timezone,
            ],
            'country' => CountryResource::make($this->whenLoaded('country')),
            'country_id' => CountryResource::make($this->whenLoaded('country')),
            'address' => AddressResource::make($this->whenLoaded('address')),
            'is_active' => $this->is_active ? true : false,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'operatorPaymentGateways' => OperatorPaymentGatewayResource::collection($this->whenLoaded('operatorPaymentGateways')),
        ];
    }
}
