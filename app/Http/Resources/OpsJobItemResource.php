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
        // When the item is frozen (10 min after stock-in) we serve a few fields
        // from the stored snapshot instead of re-deriving them live: the stock
        // action badge, the channel-error/tally verdict, coin float and the
        // product-mapping labels. Cash, amounts and counts are intentionally
        // NOT frozen — they keep their original live behaviour. $pick() returns
        // the frozen value when present, else the live one.
        $frozen = $this->frozen_at ? ($this->frozen_snapshot ?? []) : null;
        $pick = function (string $key, $live) use ($frozen) {
            return ($frozen !== null && array_key_exists($key, $frozen)) ? $frozen[$key] : $live;
        };

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
            'cms_transaction_at' => isset($this->cms_transaction_at) ? $this->cms_transaction_at->format('ymd h:i a (D)') : '',
            'cms_transaction_id' => $this->cms_transaction_id,
            'cms_transaction_by' => $this->cms_transaction_by,
            'cmsTransactionBy' => UserResource::make($this->whenLoaded('cmsTransactionBy')),
            'code' => $this->code,
            'completed_at' => isset($this->completed_at) ? $this->completed_at->format('ymd h:i a (D)') : '',
            'completed_by' => $this->completed_by,
            'completedBy' => UserResource::make($this->whenLoaded('completedBy')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:i a (D)') : '',
            'created_by' => $this->created_by,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'delta_cash_amount' => isset($this->delta_cash_amount) ? $this->delta_cash_amount / 100 : 0,
            'delivery_postcode' => isset($this->delivery_postcode) ? $this->delivery_postcode : null,
            'is_cash_collected' => $this->is_cash_collected,
            'is_ignore_limit' => $this->is_ignore_limit,
            'name' => $this->name,
            'ops_job_id' => $this->ops_job_id,
            'picked_amount' => isset($this->picked_amount) ? $this->picked_amount / 100 : 0,
            'picked_count' => isset($this->picked_count) ? $this->picked_count : 0,
            'previous_ops_job_item_id' => $this->previous_ops_job_item_id,
            'qty' => $this->qty,
            'remarks' => $this->remarks,
            'remarks_updated_at' => isset($this->remarks_updated_at) ? $this->remarks_updated_at->format('ymd h:i a (D)') : '',
            'remarks_updated_by' => $this->remarks_updated_by,
            'remarksUpdatedBy' => UserResource::make($this->whenLoaded('remarksUpdatedBy')),
            'sequence' => $this->sequence,
            'status_at' => isset($this->status_at) ? Carbon::parse($this->status_at)->format('ymd h:i a (D)') : '',
            'statusBy' => UserResource::make($this->whenLoaded('statusBy')),
            'status' => $this->status,
            'status_name' => OpsJob::STATUS_MAPPINGS[$this->status],
            'stock_action_type' => $pick('stock_action_type', $this->stock_action_type),
            'refillable_amount' => isset($this->refillable_amount) ? $this->refillable_amount / 100 : 0,
            'refillable_count' => isset($this->refillable_count) ? $this->refillable_count : 0,
            'stock_in_amount' => isset($this->stock_in_amount) ? $this->stock_in_amount / 100 : 0,
            'stock_in_count' => isset($this->stock_in_count) ? $this->stock_in_count : 0,
            'temp_cash_amount_from_vmc' => isset($this->temp_cash_amount_from_vmc) ? $this->temp_cash_amount_from_vmc : null,
            'total_cash_amount' => isset($this->total_cash_amount) ? $this->total_cash_amount / 100 : 0,
            'total_cash_amount_from_vmc' => isset($this->total_cash_amount_from_vmc) ? $this->total_cash_amount_from_vmc / 100 : 0,
            'delivered_by' => UserResource::make($this->whenLoaded('deliveredBy')),
            'notes' => $this->notes,
            // Freeze state — frontend renders the snapshot tally/coin-float for
            // frozen rows and disables editing. frozen_at is null when live.
            'frozen_at' => isset($this->frozen_at) ? $this->frozen_at->format('ymd h:i a (D)') : null,
            'is_frozen' => $this->frozen_at !== null,
            'frozen_tally_status' => $frozen !== null && array_key_exists('tally_status', $frozen) ? $frozen['tally_status'] : null,
            'frozen_coin_float' => $frozen !== null && array_key_exists('coin_float', $frozen) ? $frozen['coin_float'] : null,
            'frozen_mapping_current_name' => $frozen !== null && array_key_exists('mapping_current_name', $frozen) ? $frozen['mapping_current_name'] : null,
            'frozen_mapping_upcoming_via_current' => $frozen !== null && array_key_exists('mapping_upcoming_via_current', $frozen) ? $frozen['mapping_upcoming_via_current'] : null,
            // Legacy fallback: snapshots frozen before the via/direct split stored a
            // single pre-resolved `mapping_upcoming_name` (viaCurrent-preferred,
            // N/A-filtered). Surface it via the direct slot so both consumers
            // (OpsJob/Edit prefers direct; CustomerIndex falls through to direct)
            // still render the "New" badge on historical frozen rows.
            'frozen_mapping_upcoming_direct' => $frozen !== null && array_key_exists('mapping_upcoming_direct', $frozen)
                ? $frozen['mapping_upcoming_direct']
                : ($frozen !== null && array_key_exists('mapping_upcoming_name', $frozen) ? $frozen['mapping_upcoming_name'] : null),
            'frozen_mapping_remarks' => $frozen !== null && array_key_exists('mapping_remarks', $frozen) ? $frozen['mapping_remarks'] : null,
            'frozen_channel_error_logs' => $frozen !== null && array_key_exists('channel_error_logs', $frozen) ? $frozen['channel_error_logs'] : null,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'opsJob' => OpsJobResource::make($this->whenLoaded('opsJob')),
            'opsJobItemChannels' => OpsJobItemChannelResource::collection($this->whenLoaded('opsJobItemChannels')),
            'picked_at' => isset($this->picked_at) ? $this->picked_at->format('ymd h:i a (D)') : '',
            'picked_by' => $this->picked_by,
            'pickedBy' => UserResource::make($this->whenLoaded('pickedBy')),
            'previous_ops_job_item' => $this->previous_ops_job_item,
            'previousOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('previousOpsJobItem')),
            'updated_by' => UserResource::make($this->whenLoaded('updatedBy')),
            'undo_picked_at' => isset($this->undo_picked_at) ? $this->undo_picked_at->format('ymd h:i a (D)') : '',
            'undo_picked_by' => $this->undo_picked_by,
            'undoPickedBy' => UserResource::make($this->whenLoaded('undoPickedBy')),
            'undo_completed_at' => isset($this->undo_completed_at) ? $this->undo_completed_at->format('ymd h:i a (D)') : '',
            'undo_completed_by' => $this->undo_completed_by,
            'undoCompletedBy' => UserResource::make($this->whenLoaded('undoCompletedBy')),
            'undo_verified_at' => isset($this->undo_verified_at) ? $this->undo_verified_at->format('ymd h:i a (D)') : '',
            'undo_verified_by' => $this->undo_verified_by,
            'undoVerifiedBy' => UserResource::make($this->whenLoaded('undoVerifiedBy')),
            'undo_flagged_at' => isset($this->undo_flagged_at) ? $this->undo_flagged_at->format('ymd h:i a (D)') : '',
            'undo_flagged_by' => $this->undo_flagged_by,
            'undoFlaggedBy' => UserResource::make($this->whenLoaded('undoFlaggedBy')),
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('ymd h:i a (D)') : '',
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => $this->vend_id,
            'vend_channel_record_id' => $this->vend_channel_record_id,
            'vendChannelRecord' => VendChannelRecordResource::make($this->whenLoaded('vendChannelRecord')),
            'verified_at' => isset($this->verified_at) ? $this->verified_at->format('ymd h:i a (D)') : '',
            'verified_by' => $this->verified_by,
            'verifiedBy' => UserResource::make($this->whenLoaded('verifiedBy')),
        ];
    }
}
