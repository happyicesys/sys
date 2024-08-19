<?php

namespace App\Http\Resources;

use App\Models\OpsJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'ref_id' => $this->id + 25000,
            'acc_vend_transactions_amount' => isset($this->acc_vend_transactions_amount) ? $this->acc_vend_transactions_amount / 100 : 0,
            'acc_vend_transactions_cash_amount' => isset($this->acc_vend_transactions_cash_amount) ? $this->acc_vend_transactions_cash_amount / 100 : 0,
            'acc_vend_transactions_cashless_amount' => isset($this->acc_vend_transactions_cashless_amount) ? $this->acc_vend_transactions_cashless_amount / 100 : 0,
            'acc_vend_transactions_promo_amount' => isset($this->acc_vend_transactions_promo_amount) ? $this->acc_vend_transactions_promo_amount / 100 : 0,
            'acc_vend_transactions_count' => isset($this->acc_vend_transactions_count) ? $this->acc_vend_transactions_count : 0,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'cash_amount' => $this->cash_amount,
            'cashless_amount' => $this->cashless_amount,
            'cms_transaction_id' => $this->cms_transaction_id,
            'code' => $this->code,
            'completed_at' => isset($this->completed_at) ? $this->completed_at->format('ymd h:i a') : '',
            'completed_by' => $this->completed_by,
            'completedBy' => UserResource::make($this->whenLoaded('completedBy')),
            'name' => $this->name,
            'picked_amount' => isset($this->picked_amount) ? $this->picked_amount/100 : 0,
            'picked_count' => isset($this->picked_count) ? $this->picked_count : 0,
            'previous_ops_job_item_id' => $this->previous_ops_job_item_id,
            'qty' => $this->qty,
            'remarks' => $this->remarks,
            'sequence' => $this->sequence,
            'status' => $this->status,
            'status_name' => OpsJob::STATUS_MAPPINGS[$this->status],
            'stock_in_amount' => isset($this->stock_in_amount) ? $this->stock_in_amount/100 : 0,
            'stock_in_count' => isset($this->stock_in_count) ? $this->stock_in_count : 0,
            'temp_cash_amount_from_vmc' => isset($this->temp_cash_amount_from_vmc) ? $this->temp_cash_amount_from_vmc : 0,
            'total_cash_amount' => isset($total_cash_amount) ? $this->total_cash_amount : 0,
            'total_cash_amount_from_vmc' => isset($this->total_cash_amount_from_vmc) ? $this->total_cash_amount_from_vmc/100 : 0,
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'delivered_by' => UserResource::make($this->whenLoaded('deliveredBy')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'opsJob' => OpsJobResource::make($this->whenLoaded('opsJob')),
            'opsJobItemChannels' => OpsJobItemChannelResource::collection($this->whenLoaded('opsJobItemChannels')),
            'picked_at' => isset($this->picked_at) ? $this->picked_at->format('ymd h:i a') : '',
            'picked_by' => $this->picked_by,
            'pickedBy' => UserResource::make($this->whenLoaded('pickedBy')),
            'previous_ops_job_item' => $this->previous_ops_job_item,
            'previousOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('previousOpsJobItem')),
            'updated_by' => UserResource::make($this->whenLoaded('updatedBy')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:i a') : '',
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('ymd h:i a') : '',
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => $this->vend_id,
            'vend_channel_record_id' => $this->vend_channel_record_id,
            'vendChannelRecord' => VendChannelRecordResource::make($this->whenLoaded('vendChannelRecord')),
        ];
    }
}
