<?php

namespace App\Http\Resources;

use App\Models\StockCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $channels = $this->relationLoaded('channels') ? $this->channels : null;

        return [
            'id' => $this->id,
            'stop_type' => StockCheck::STOP_TYPE,
            'code' => $this->code,
            'display_code' => $this->display_code,
            'ops_job_id' => $this->ops_job_id,
            'sequence' => $this->sequence,
            'status' => (int) $this->status,
            'status_name' => $this->statusName(),
            'is_random' => $this->is_random,
            'sample_size' => $this->sample_size,
            'product_filter' => $this->product_filter ?? [],
            'remarks' => $this->remarks,
            ...StopResourceFields::machine($this->resource),
            'channels_count' => $channels?->count(),
            'mismatch_count' => $channels?->filter->hasVariance()->count(),
            'unsynced_mismatch_count' => $channels?->filter(fn ($c) => $c->hasVariance() && ! $c->isSynced())->count(),
            'variance_qty' => $this->isCompleted() ? $channels?->sum('variance_qty') : null,
            'variance_value' => $this->isCompleted() ? $channels?->sum(fn ($c) => (int) $c->varianceValueCents()) : null, // cents
            'channels' => StockCheckChannelResource::collection($this->whenLoaded('channels')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'ops_job' => $this->whenLoaded('opsJob', fn () => StopResourceFields::opsJob($this->opsJob)),
            'created_at' => $this->created_at?->format('ymd h:i a'),
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'counted_at' => $this->counted_at?->format('ymd h:i a'),
            'counted_by_name' => $this->whenLoaded('countedBy', fn () => $this->countedBy?->name),
            'synced_at' => $this->synced_at?->format('ymd h:i a'),
            'synced_by_name' => $this->whenLoaded('syncedBy', fn () => $this->syncedBy?->name),
            'cancelled_at' => $this->cancelled_at?->format('ymd h:i a'),
        ];
    }
}
