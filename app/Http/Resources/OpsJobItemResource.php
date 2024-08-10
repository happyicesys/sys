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
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'cash_amount' => $this->cash_amount,
            'cashless_amount' => $this->cashless_amount,
            'cms_transaction_id' => $this->cms_transaction_id,
            'code' => $this->code,
            'completed_at' => isset($this->completed_at) ? $this->completed_at->format('ymd h:i a') : '',
            'completed_by' => $this->completed_by,
            'completedBy' => UserResource::make($this->whenLoaded('completedBy')),
            'name' => $this->name,
            'qty' => $this->qty,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'status_name' => OpsJob::STATUS_MAPPINGS[$this->status],
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'delivered_by' => UserResource::make($this->whenLoaded('deliveredBy')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'opsJob' => OpsJobResource::make($this->whenLoaded('opsJob')),
            'opsJobItemChannels' => OpsJobItemChannelResource::collection($this->whenLoaded('opsJobItemChannels')),
            'picked_at' => isset($this->picked_at) ? $this->picked_at->format('ymd h:i a') : '',
            'picked_by' => $this->picked_by,
            'pickedBy' => UserResource::make($this->whenLoaded('pickedBy')),
            'updated_by' => UserResource::make($this->whenLoaded('updatedBy')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:i a') : '',
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('ymd h:i a') : '',
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => $this->vend_id,
        ];
    }
}
