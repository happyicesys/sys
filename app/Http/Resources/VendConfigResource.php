<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendConfigResource extends JsonResource
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
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'desc' => $this->desc,
            'name' => $this->name,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => $this->operator_id,
            'vendPrefixes' => VendPrefixResource::collection($this->whenLoaded('vendPrefixes')),
        ];
    }
}
