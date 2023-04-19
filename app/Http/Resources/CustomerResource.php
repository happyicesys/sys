<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'category_id' => CategoryResource::make($this->whenLoaded('category')),
            'code' => $this->code,
            'created_at' => Carbon::parse($this->created_at)->toDateString(),
            'created_by' => $this->created_by,
            'deactivated_at' => $this->deactivated_at,
            'name' => $this->name,
            'first_transaction_id' => $this->first_transaction_id,
            'handled_by' => $this->handled_by,
            'is_active' => $this->is_active,
            'location_type_id' => $this->location_type_id,
            'parent_id' => $this->parent_id,
            'pay_term_id' => $this->pay_term_id,
            'profile_id' => $this->profile_id,
            'price_template_id' => $this->price_template_id,
            'ops_note' => $this->ops_note,
            'remarks' => $this->remarks,
            'status_id' => $this->status_id,
            'updated_at' => Carbon::parse($this->updated_at)->toDateString(),
            'updated_by' => $this->updated_by,
            'zone_id' => $this->zone_id,
            'accountManager' => UserResource::make($this->whenLoaded('handledBy')),
            'addresses' => AddressResource::make($this->whenLoaded('addresses')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'billingAddress' => AddressResource::make($this->whenLoaded('billingAddress')),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'deliveryAddress' => AddressResource::make($this->whenLoaded('deliveryAddress')),
            'firstTransaction' => TransactionResource::make($this->whenLoaded('firstTransaction')),
            'status' => StatusResource::make($this->whenLoaded('status')),
            'tags' => TagResource::collection($this->whenLoaded('tagBindings')),
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
