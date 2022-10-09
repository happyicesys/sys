<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'total_amount' => $this->total_amount,
            'subtotal_amount' => $this->subtotal_amount,
            'total_qty' => $this->total_qty,
            'deals_obj' => $this->deals_obj,
            'customer_id' => CustomerResource::make($this->whenLoaded('customer_id')),
            'order_date' => $this->order_date,
            'delivery_date' => $this->delivery_date,
            'payment_date' => $this->payment_date,
            'po_num' => $this->po_num,
            'inner_remarks' => $this->inner_remarks,
            'remarks' => $this->remarks,
            'pay_method_id' => PaymentMethodResource::make($this->whenLoaded('pay_method_id')),
            'delivered_by' => UserResource::make($this->whenLoaded('delivered_by')),
            'created_by' => UserResource::make($this->whenLoaded('created_by')),
            'updated_by' => UserResource::make($this->whenLoaded('updated_by')),
            'handled_by' => UserResource::make($this->whenLoaded('handled_by')),
        ];
    }
}
