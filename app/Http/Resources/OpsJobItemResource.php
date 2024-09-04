<?php

namespace App\Http\Resources;

use App\Models\OpsJob;
use Carbon\Carbon;
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
            'cash_amount' => isset($this->cash_amount) ? $this->cash_amount : null,
            'cashless_amount' => $this->cashless_amount,
            'cms_transaction_at' => isset($this->cms_transaction_at) ? $this->cms_transaction_at->format('ymd h:i:sa') : '',
            'cms_transaction_id' => $this->cms_transaction_id,
            'cms_transaction_by' => $this->cms_transaction_by,
            'cmsTransactionBy' => UserResource::make($this->whenLoaded('cmsTransactionBy')),
            'code' => $this->code,
            'completed_at' => isset($this->completed_at) ? $this->completed_at->format('ymd h:i:sa') : '',
            'completed_by' => $this->completed_by,
            'completedBy' => UserResource::make($this->whenLoaded('completedBy')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:i:sa') : '',
            'created_by' => $this->created_by,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'delta_cash_amount' => isset($this->delta_cash_amount) ? $this->delta_cash_amount / 100 : 0,
            'is_cash_collected' => $this->is_cash_collected,
            'name' => $this->name,
            'ops_job_id' => $this->ops_job_id,
            'picked_amount' => isset($this->picked_amount) ? $this->picked_amount/100 : 0,
            'picked_count' => isset($this->picked_count) ? $this->picked_count : 0,
            'previous_ops_job_item_id' => $this->previous_ops_job_item_id,
            'qty' => $this->qty,
            'remarks' => $this->remarks,
            'remarks_updated_at' => isset($this->remarks_updated_at) ? $this->remarks_updated_at->format('ymd h:i:sa') : '',
            'remarks_updated_by' => $this->remarks_updated_by,
            'remarksUpdatedBy' => UserResource::make($this->whenLoaded('remarksUpdatedBy')),
            'sequence' => $this->sequence,
            'status_at' => isset($this->status_at) ? Carbon::parse($this->status_at)->format('ymd h:i:sa') : '',
            'statusBy' => UserResource::make($this->whenLoaded('statusBy')),
            'status' => $this->status,
            'status_name' => OpsJob::STATUS_MAPPINGS[$this->status],
            'stock_in_amount' => isset($this->stock_in_amount) ? $this->stock_in_amount/100 : 0,
            'stock_in_count' => isset($this->stock_in_count) ? $this->stock_in_count : 0,
            'temp_cash_amount_from_vmc' => isset($this->temp_cash_amount_from_vmc) ? $this->temp_cash_amount_from_vmc : null,
            'total_cash_amount' => isset($total_cash_amount) ? $this->total_cash_amount : 0,
            'total_cash_amount_from_vmc' => isset($this->total_cash_amount_from_vmc) ? $this->total_cash_amount_from_vmc/100 : 0,
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'delivered_by' => UserResource::make($this->whenLoaded('deliveredBy')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'opsJob' => OpsJobResource::make($this->whenLoaded('opsJob')),
            'opsJobItemChannels' => OpsJobItemChannelResource::collection($this->whenLoaded('opsJobItemChannels')),
            'picked_at' => isset($this->picked_at) ? $this->picked_at->format('ymd h:i:sa') : '',
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
            'verified_at' => isset($this->verified_at) ? $this->verified_at->format('ymd h:i:sa') : '',
            'verified_by' => $this->verified_by,
            'verifiedBy' => UserResource::make($this->whenLoaded('verifiedBy')),
        ];
    }
}
