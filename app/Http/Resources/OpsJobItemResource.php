<?php

namespace App\Http\Resources;

use App\Models\OpsJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'qty' => $this->qty,
            'status' => OpsJob::STATUS_MAPPINGS[$this->status],
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'delivered_by' => UserResource::make($this->whenLoaded('deliveredBy')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'picked_at' => isset($this->picked_at) ? $this->picked_at->format('ymd h:i a') : '',
            'picked_by' => UserResource::make($this->whenLoaded('pickedBy')),
            'updated_by' => UserResource::make($this->whenLoaded('updatedBy')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:i a') : '',
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('ymd h:i a') : '',
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => $this->vend_id,
        ];
    }
}
