<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendTransactionItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    /**
     * May the current viewer see this item's cost price?
     *
     * Unrestricted viewers always can (ProductAccess::current() returns null).
     *
     * Deliberately allowsItem() and NOT allows(..., $this->product_id): the
     * item's own product_id is NULL on ~1.7k live rows, and for most of those
     * the channel it came out of does carry one. Deciding on the raw column
     * alone called those items "not yours" even when the channel was, which is
     * the exact screen-vs-export split this helper exists to close - the two
     * CSV jobs and the Excel export all resolve through the same call.
     *
     * Needs vendChannel eager-loaded for the fallback; VendController's
     * transactionIndex loads it. A caller that forgets simply lazy-loads.
     */
    protected function productAccessAllowsThisItem(): bool
    {
        return \App\Support\ProductAccess::allowsItem(
            \App\Support\ProductAccess::current(),
            $this->resource
        );
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_refunded' => $this->is_refunded,
            // Per-item refund-request badge — only populated (in VendController
            // transactionIndex) when a multiple-purchase refund targets this
            // specific line item. Null on every other item row.
            'refund_request_id' => $this->refund_request_id ?? null,
            'refund_request_reference' => $this->refund_request_reference ?? null,
            'refund_request_status' => $this->refund_request_status ?? null,
            'refund_request_is_dropped' => (bool) ($this->refund_request_is_dropped ?? false),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'product_id' => $this->product_id,
            // "Access Product(s)": a mixed basket is shown whole on purpose, but a
            // partner must not read a competitor's COST price out of it. This is
            // the same value ExportVendTransactionCsv::maskedUnitCost blanks in
            // the CSV - mask it in the payload too, or it is one devtools tab away.
            'unit_cost' => $this->productAccessAllowsThisItem() ? $this->unit_cost : null,
            'unitCost' => $this->when(
                $this->productAccessAllowsThisItem(),
                fn () => UnitCostResource::make($this->whenLoaded('unitCost'))
            ),
            'unit_cost_id' => $this->productAccessAllowsThisItem() ? $this->unit_cost_id : null,
            'vendChannel' => VendChannelResource::make($this->whenLoaded('vendChannel')),
            'vend_channel_id' => $this->vend_channel_id,
            'vend_channel_code' => $this->vend_channel_code,
            'vend_channel_error_code' => $this->vend_channel_error_code,
            'vendChannelError' => VendChannelErrorResource::make($this->whenLoaded('vendChannelError')),
            'vend_channel_error_id' => $this->vend_channel_error_id,
            'vendTransaction' => VendTransactionResource::make($this->whenLoaded('vendTransaction')),
            'vend_transaction_id' => $this->vend_transaction_id,
        ];
    }
}
