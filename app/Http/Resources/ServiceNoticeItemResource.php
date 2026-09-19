<?php

namespace App\Http\Resources;

use App\Models\ServiceNoticeItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceNoticeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'status' => (int) $this->status,
            'status_name' => $this->statusName(),
            'is_resolved' => (int) $this->status !== ServiceNoticeItem::STATUS_NEW,
            'desc' => $this->desc,
            'desc_before' => $this->desc_before,
            'desc_after' => $this->desc_after,
            'incomplete_reason' => $this->incomplete_reason,
            'status_changed_at' => $this->status_changed_at?->format('ymd h:i a'),
            'status_changed_by_name' => $this->whenLoaded('statusChangedBy', fn () => $this->statusChangedBy?->name),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
