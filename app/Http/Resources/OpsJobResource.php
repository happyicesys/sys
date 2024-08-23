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
            'acc_vend_transactions_amount' => isset($this->acc_vend_transactions_amount) ? $this->acc_vend_transactions_amount/100 : 0,
            'acc_vend_transactions_count' => isset($this->acc_vend_transactions_count) ? $this->acc_vend_transactions_count : 0,
            'code' => $this->code,
            'date' => $this->date,
            'date_formatted' => $this->date->format('ymd'),
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
                    : (Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays() < 0 ? ('Next ' . ceil(abs(Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays())) . ' days') : ('Last ' . ceil(abs(Carbon::parse($this->date)->setTimezone($this->getUserTimezone())->diffInDays())) . ' days'))
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
            'ops_job_items_delivered_count' => isset($this->ops_job_items_delivered_count) ? $this->ops_job_items_delivered_count : 0,
            'ops_job_items_delivered_count_percentage' => isset($this->ops_job_items_delivered_count_percentage) ? round($this->ops_job_items_delivered_count_percentage) : 0,
            'ops_job_items_picked_count' => isset($this->ops_job_items_picked_count) ? $this->ops_job_items_picked_count : 0,
            'ops_job_items_picked_count_percentage' => isset($this->ops_job_items_picked_count_percentage) ? round($this->ops_job_items_picked_count_percentage) : 0,
            'ops_job_items_verified_count' => isset($this->ops_job_items_verified_count) ? $this->ops_job_items_verified_count : 0,
            'ops_job_items_verified_count_percentage' => isset($this->ops_job_items_verified_count_percentage) ? round($this->ops_job_items_verified_count_percentage) : 0,
            'picked_at' => $this->picked_at,
            'pickedBy' => UserResource::make($this->whenLoaded('pickedBy')),
            'picked_amount' => isset($this->picked_amount) ? $this->picked_amount/100 : 0,
            'picked_count' => isset($this->picked_count) ? $this->picked_count : 0,
            'picked_cost' => isset($this->picked_cost) ? $this->picked_cost/100 : 0,
            'stock_in_amount' => isset($this->stock_in_amount) ? $this->stock_in_amount/100 : 0,
            'stock_in_count' => isset($this->stock_in_count) ? $this->stock_in_count : 0,
            'total_cash_amount' => isset($this->total_cash_amount) ? $this->total_cash_amount/100 : 0,
            'total_cash_amount_from_vmc' => isset($this->total_cash_amount_from_vmc) ? $this->total_cash_amount_from_vmc/100 : 0,
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:i a') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('ymd h:i a') : null,
        ];
    }
}
