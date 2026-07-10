<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayoutGroupResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'bank_account_no' => $this->bank_account_no,
            'bank_account_name' => $this->bank_account_name,
            'is_active' => (bool) $this->is_active,
            'operators' => $this->whenLoaded('operators', fn () => $this->operators
                ->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'code' => $o->code])->values()),
            'operator_ids' => $this->whenLoaded('operators', fn () => $this->operators->pluck('id')->values()),
            'operators_count' => $this->whenLoaded('operators', fn () => $this->operators->count()),
            'created_at' => $this->created_at,
        ];
    }
}
