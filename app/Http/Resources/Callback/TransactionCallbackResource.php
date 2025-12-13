<?php

namespace App\Http\Resources\Callback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionCallbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event' => 'transaction_upload',
            'transaction_id' => $this->id,
            'order_id' => $this->order_id,
            'amount' => $this->amount, // Amount in cents/lowest unit
            'qty' => $this->qty,
            'vend_code' => $this->vend?->code,
            'transaction_datetime' => $this->transaction_datetime instanceof \Carbon\Carbon
                ? $this->transaction_datetime->toIso8601String()
                : $this->transaction_datetime,
            'payment_method' => $this->paymentMethod?->name,
            'product_id' => $this->product_id,
            'items' => $this->vendTransactionItems->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'price' => $item->unit_price_amount,
                    'vend_channel_code' => $item->vend_channel_code,
                ];
            }),
            // Add any other fields if necessary
        ];
    }
}
