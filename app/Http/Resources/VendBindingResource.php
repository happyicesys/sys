<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class VendBindingResource extends JsonResource
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
            'begin_date' => Carbon::parse($this->begin_date)->toDateString(),
            'termination_date' => Carbon::parse($this->termination_date)->toDateString(),
            'is_active' => $this->is_active,
            'is_rental' => $this->is_rental,
            'is_profit_sharing' => $this->is_profit_sharing,
            'is_profit_sharing_percentage' => $this->is_profit_sharing_percentage,
            'is_both_utility_comm' => $this->is_both_utility_comm,
            'product_unit_price' => $this->product_unit_price,
            'rental' => $this->is_rental ? $this->is_rental/ 100 : null,
            'profit_sharing' => $this->profit_sharing ? $this->profit_sharing/ 100 : null,
            'utilities' => $this->utilities ? $this->utilities/ 100 : null,
            'adjustment_rate' => $this->adjustment_rate ? $this->adjustment_rate/ 100 : null,
            'is_pwp' => $this->is_pwp,
            'pwp_adjustment_rate' => $this->pwp_adjustment_rate ? $this->pwp_adjustment_rate/ 100 : null,
            'customer_id' => $this->customer_id,
            'vend_id' => $this->vend_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
