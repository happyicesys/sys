<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
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
            'code' => isset($this->code) ? $this->code : null,
            'name' => isset($this->name) ? $this->name : null,
            'count' => isset($this->count) ? $this->count : 0,
            'amount' => isset($this->amount) ? $this->amount/100 : 0,
        ];
    }
}
