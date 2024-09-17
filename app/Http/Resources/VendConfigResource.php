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
            'is_active' => $this->is_active ? true : false,
            'name' => $this->name,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => $this->operator_id,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'vends_count' => isset($this->vends_count) ? $this->vends_count : 0,
            'vendConfigCompatibles' => VendConfigResource::collection($this->whenLoaded('vendConfigCompatibles')),
            'vendConfigCompatibleWith' => VendConfigResource::collection($this->whenLoaded('vendConfigCompatibleWith')),
            'vendPrefixes' => VendPrefixResource::collection($this->whenLoaded('vendPrefixes')),
            'version' => $this->version,
        ];
    }
}
