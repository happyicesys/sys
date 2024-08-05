<?php

namespace App\Http\Resources;

use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobResource extends JsonResource
{
    use GetUserTimezone;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'code' => $this->code,
            'date' => $this->date->format('ymd'),
            'date_diff_human' => isset($this->date)
            ? (
                (
                    Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays() > 0
                    && Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays() < 1
                )
                ? 'today'
                : (
                    (
                        Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays() > -1
                        && Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays() < 0
                    )
                    ? 'tomorrow'
                    : Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffForHumans(['options' => Carbon::ONE_DAY_WORDS])
                )
            )
            : null,
            'date_diff_count' => isset($this->date) ? Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays() : null,
            'status' => $this->status,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'deliveredBy' => UserResource::make($this->whenLoaded('deliveredBy')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'opsJobItems' => OpsJobItemResource::collection($this->whenLoaded('opsJobItems')),
            'ops_job_items_count' => isset($this->ops_job_items_count) ? $this->ops_job_items_count : 0,
            'picked_at' => $this->picked_at,
            'pickedBy' => UserResource::make($this->whenLoaded('pickedBy')),
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at->format('ymd h:i a'),
            'updated_at' => $this->updated_at->format('ymd h:i a'),
        ];
    }
}
