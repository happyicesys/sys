<?php

namespace App\Http\Resources;

use App\Models\ServiceNotice;
use App\Models\ServiceNoticeItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceNoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stop_type' => ServiceNotice::STOP_TYPE,
            'code' => $this->code,
            'display_code' => $this->display_code,
            'ops_job_id' => $this->ops_job_id,
            'sequence' => $this->sequence,
            'status' => (int) $this->status,
            'status_name' => $this->statusName(),
            'remarks' => $this->remarks,
            ...StopResourceFields::machine($this->resource),
            // Progress for the job table: "2/3" = verdicts given / items.
            'items_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'items_resolved_count' => $this->whenLoaded('items', fn () => $this->items
                ->where('status', '!=', ServiceNoticeItem::STATUS_NEW)->count()),
            'items_incomplete_count' => $this->whenLoaded('items', fn () => $this->items
                ->where('status', ServiceNoticeItem::STATUS_INCOMPLETE)->count()),
            'items' => ServiceNoticeItemResource::collection($this->whenLoaded('items')),
            'ops_job' => $this->whenLoaded('opsJob', fn () => StopResourceFields::opsJob($this->opsJob)),
            'created_at' => $this->created_at?->format('ymd h:i a'),
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'completed_at' => $this->completed_at?->format('ymd h:i a'),
            'completed_by_name' => $this->whenLoaded('completedBy', fn () => $this->completedBy?->name),
            'cancelled_at' => $this->cancelled_at?->format('ymd h:i a'),
        ];
    }
}
