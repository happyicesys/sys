<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayLogResource extends JsonResource
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
            'amount' => $this->amount,
            'approved_at' => $this->approved_at,
            'approved_at_formatted' => $this->approved_at ? Carbon::parse($this->approved_at)->format('ymd h:ia') : null,
            'created_at' => $this->created_at,
            'is_dispensed' => $this->is_dispensed,
            'method' => $this->method,
            'order_id' => $this->order_id,
            'qr_text' => $this->qr_text,
            'qr_url' => $this->qr_url,
            'ref_id' => $this->ref_id,
            'operatorPaymentGateway' => OperatorPaymentGatewayResource::make($this->whenLoaded('operatorPaymentGateway')),
            'paymentGateway' => PaymentGatewayResource::make($this->whenLoaded('paymentGateway')),
            'status' => $this->status,
            'txn_src' => $this->txn_src,
            'vend_channels_json' => $this->vend_channels_json,
            'vend_code' => $this->vend_code,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => $this->vend_id,
            'vendTransaction' => VendTransactionResource::make($this->whenLoaded('vendTransaction')),
        ];
    }
}
