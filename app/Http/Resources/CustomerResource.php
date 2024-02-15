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
            'customer_json' => $this->customer_json,
            'name' => $this->name,
            'first_transaction_id' => $this->first_transaction_id,
            'is_active' => $this->is_active,
            'location_type_id' => $this->location_type_id,
            'person_id' => $this->person_id,
            'profile_id' => $this->profile_id,
            'status_id' => $this->status_id,
            'updated_at' => Carbon::parse($this->updated_at)->toDateString(),
            'virtual_customer_code' => $this->virtual_customer_code,
            'virtual_customer_prefix' => $this->virtual_customer_prefix,
            'zone_id' => $this->zone_id,
            'accountManager' => UserResource::make($this->whenLoaded('handledBy')),
            'addresses' => AddressResource::make($this->whenLoaded('addresses')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'billingAddress' => AddressResource::make($this->whenLoaded('billingAddress')),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'deliveryAddress' => AddressResource::make($this->whenLoaded('deliveryAddress')),
            'firstTransaction' => TransactionResource::make($this->whenLoaded('firstTransaction')),
            'status' => StatusResource::make($this->whenLoaded('status')),
            'tags' => TagResource::collection($this->whenLoaded('tagBindings')),
            'vendBindings' => VendBindingResource::collection($this->whenLoaded('vendBindings')),
        ];
    }
}
