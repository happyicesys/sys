<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModemUnitResource extends JsonResource
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
            'imei' => $this->imei,
            'modem_type_id' => $this->modem_type_id,
            'is_active' => $this->is_active ? true : false,
            'is_online' => $this->is_online ? true : false,
            'last_updated_at' => isset($this->last_updated_at)
            ? Carbon::parse($this->last_updated_at)->shortRelativeDiffForHumans()
            : null,
            'modemType' => new ModemTypeResource($this->whenLoaded('modemType')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
