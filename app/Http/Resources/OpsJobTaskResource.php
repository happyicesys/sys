<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'ops_job_id'    => $this->ops_job_id,
            'sequence'      => $this->sequence,
            'task_name'     => $this->task_name,
            'address'       => $this->address,
            'postcode'      => $this->postcode,
            'ops_note'      => $this->ops_note,
            'ref_url'       => $this->ref_url,
            // value accessor already divides by 100 (stored as cents)
            'value'         => $this->value,
            'qty'           => $this->qty,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'created_by_name' => $this->createdBy?->name,
            'created_at'    => $this->created_at?->format('ymd h:i a'),
            'updated_at'    => $this->updated_at?->format('ymd h:i a'),
        ];
    }
}
