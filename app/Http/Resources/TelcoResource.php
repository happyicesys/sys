<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TelcoResource extends JsonResource
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
            'name' => $this->name,
            'desc' => $this->desc,
            'remarks' => $this->remarks,
            'usage_provider' => $this->usage_provider,
            'usage_endpoint' => $this->usage_endpoint,
            // Retired packages stay listed in filters; the pickers that ASSIGN
            // a package (Simcard/Form) drop them. Cast so the Vue side can
            // compare with === (the column is a tinyint over the wire).
            'is_active' => (bool) ($this->is_active ?? true),
            // One of Telco::COLORS, or null for the default badge tint.
            'color' => $this->color,
            // "Active / total" counts — only present on the Telco Index, which
            // is the one query that withCount()s them.
            'simcards_count' => isset($this->simcards_count) ? (int) $this->simcards_count : null,
            'simcards_on_machine_count' => isset($this->simcards_on_machine_count) ? (int) $this->simcards_on_machine_count : null,
        ];
    }
}
