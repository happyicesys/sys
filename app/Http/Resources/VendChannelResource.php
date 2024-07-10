<?php

namespace App\Http\Resources;

use App\Http\Resources\VendChannelErrorLogResource;
use Illuminate\Http\Resources\Json\JsonResource;

class VendChannelResource extends JsonResource
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
            'discount_group' => $this->discount_group,
            'error_rate_json' => $this->error_rate_json,
            'qty' => $this->qty,
            'qty_sold_at' => $this->qty_sold_at,
            'qty_sold_at_formatted' => $this->qty_sold_at ? $this->qty_sold_at->format('ymd h:ia') : '',
            'qty_restocked_at' => $this->qty_restocked_at,
            'qty_not_available_duration' => $this->qty_not_available_duration,
            'locked_qty' => $this->locked_qty,
            'capacity' => $this->capacity,
            'amount' => $this->amount ? $this->amount/ 100 : 0,
            'amount2' => $this->amount2 ? $this->amount2/ 100 : 0,
            'is_active' => $this->is_active ? true : false,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'vendChannelErrorLogs' => VendChannelErrorLogResource::collection($this->whenLoaded('vendChannelErrorLogs')),
            'vendTransactions' => VendTransactionResource::collection($this->whenLoaded('vendTransactions')),
            'vendThreeDaysErrorTransactions' => VendTransactionResource::collection($this->whenLoaded('vendThreeDaysErrorTransactions')),
            'vendSevenDaysErrorTransactions' => VendTransactionResource::collection($this->whenLoaded('vendSevenDaysErrorTransactions')),
        ];
    }
}
