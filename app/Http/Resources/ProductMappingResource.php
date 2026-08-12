<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductMappingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currentProductMappings' => ProductMappingResource::collection($this->whenLoaded('currentProductMappings')),
            'upcomingProductMappings' => ProductMappingResource::collection($this->whenLoaded('upcomingProductMappings')),
            'upcomingProductMapping' => ProductMappingResource::make($this->whenLoaded('upcomingProductMapping')),
            'upcoming_product_mapping_id' => $this->upcoming_product_mapping_id,
            // Formatted for the DatePicker on Edit.vue (expects 'Y-m-d');
            // null when no start date is set.
            'upcoming_product_mapping_start_date' => $this->upcoming_product_mapping_start_date
                ? $this->upcoming_product_mapping_start_date->format('Y-m-d')
                : null,
            'upcoming_product_mapping_name' => $this->relationLoaded('upcomingProductMapping') ? ($this->upcomingProductMapping ? $this->upcomingProductMapping->name : null) : null,
            'is_active' => $this->is_active ? true : false,
            'remarks' => $this->remarks,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => $this->operator_id,
            // 'productMappingItems' => ProductMappingItemResource::collection($this->whenLoaded('productMappingItems')),
            // 'productMappingItemsBySequence' => ProductMappingItemResource::collection($this->whenLoaded('productMappingItemsBySequence')),
            'productMappingItems' => ProductMappingItemResource::collection(
                $this->relationLoaded('productMappingItemsNormalSequence')
                ? $this->productMappingItemsNormalSequence
                : $this->whenLoaded('productMappingItems')
            ),
            // 'productMappingItemsJson' => $this->product_mapping_items_json,
            'selling_price_type' => $this->selling_price_type,
            // Smart-freezer planogram fields. is_smart drives the Edit-page UI
            // branch (basket grid vs the classic channel-row table); the layout
            // JSON shapes the per-basket division count (1a/1b/... vs single "3").
            'is_smart' => (bool) $this->is_smart,
            // Machine taxonomy (vending_machine / smart_freezer / smart_chiller). The Setting
            // Edit page filters the mapping dropdowns on this against the vend's machine_type.
            'machine_type' => $this->machine_type ?: 'vending_machine',
            'basket_layout_json' => $this->basket_layout_json,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'vends_count' => isset($this->vends_count) ? $this->vends_count : null,
            // Machines queued to switch TO this mapping — their EFFECTIVE upcoming
            // mapping (own vends.upcoming_product_mapping_id, else the preset upcoming
            // of the mapping they are binded to today) is this id. Only present when
            // the caller adds the `upcoming_vends_count` sub-select
            // (ProductMappingController::index).
            'upcoming_vends_count' => isset($this->upcoming_vends_count) ? $this->upcoming_vends_count : null,
            // REMOVED (2026-07-31): 'avg_mthly_sales_amount' — the summed
            // per-mapping "Avg Mthly Sales" figure (and its sortable header). Ops
            // read the group total as a per-machine number, so the column now
            // renders ONE LINE PER BINDED MACHINE, computed in
            // ProductMapping/Index.vue from each vend's site totals
            // (customer.vendTransactionTotalsJson + customer.begin_date_nullable),
            // the same way Vend/CustomerIndex.vue does it.
            'vendPrefixes' => VendPrefixResource::collection($this->whenLoaded('vendPrefixes')),
        ];
    }
}
