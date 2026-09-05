<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardTerminalUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Read-only context so ops can see a TID is already in use before
        // assigning it. `bindings` is eager-loaded already filtered to the
        // binding effective today (CardTerminalUnitController@index); the
        // binding itself is only ever created on the machine Setting/Edit page.
        $binding = $this->resource->relationLoaded('bindings')
            ? $this->bindings->first()
            : null;

        return [
            'id' => $this->id,
            'card_terminal_id' => $this->card_terminal_id,
            'card_terminal_name' => $this->company?->name,
            'terminal_id' => $this->terminal_id,
            'remarks' => $this->remarks,
            'current_vend_code' => $binding?->vend?->code,
            'current_vend_name' => $binding?->vend?->name,
        ];
    }
}
