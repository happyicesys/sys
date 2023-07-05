<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendCriteriaResource extends JsonResource
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
            'classname' => $this->classname,
            'desc' => $this->desc,
            'has_sub_criteria' => $this->has_sub_criteria,
            'name' => $this->name,
            'operator' => $this->operator,
            'options_json' => $this->options_json,
            'sequence' => $this->sequence,
            'value' => $this->value,
            'value2' => $this->value2,
            'weightage' => $this->weightage,
        ];
    }
}
