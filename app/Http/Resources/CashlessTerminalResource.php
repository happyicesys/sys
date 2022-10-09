<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashlessTerminalResource extends JsonResource
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
            'begin_date' => $this->begin_date,
            'cashless_provider_id' => $this->cashless_provider_id,
            'code' => $this->code,
            'created_by' => $this->created_by,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'is_active' => $this->is_active,
            'termination_date' => $this->termination_date,
            'updated_by' => $this->updated_by,
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
            'cashlessProvider' => CashlessProviderResource::make($this->whenLoaded('cashlessProvider')),
        ];
    }
}
