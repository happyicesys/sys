<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'avg_seven_days_count' => isset($this->avg_seven_days_count) ? $this->avg_seven_days_count : null,
            'code' => $this->code,
            // 'created_at' => $this->created_at,
            'name' => $this->name,
            'full_name' => $this->code . ' - ' . $this->name,
            'option_data' => [
                'id' => $this->id,
                'full_name' => $this->code . ' - ' . $this->name . ' ' . $this->desc,
            ],
            'remarks' => $this->remarks,
            'remarks_updated_at' => $this->remarks_updated_at,
            'remarksUpdatedBy' => UserResource::make($this->whenLoaded('remarksUpdatedBy')),
            'desc' => $this->desc,
            'is_active' => $this->is_active ? true : false,
            'is_available' => $this->is_available ? true : false,
            'is_available_updated_at' => $this->is_available_updated_at ? $this->is_available_updated_at->format('ymd h:ia') : '',
            'isAvailableUpdatedBy' => UserResource::make($this->whenLoaded('isAvailableUpdatedBy')),
            'is_commission' => $this->is_commission ? true : false,
            'is_halal' => $this->is_halal ? true : false,
            'is_healthier_choice' => $this->is_healthier_choice ? true : false,
            'is_inventory' => $this->is_inventory ? true : false,
            'is_supermarket_fee' => $this->is_supermarket_fee ? true : false,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'category_id' => $this->category_id,
            'categoryGroup' => CategoryGroupResource::make($this->whenLoaded('categoryGroup')),
            'category_group_id' => $this->category_group_id,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'isActive' => $this->is_active ? 'Yes' : 'No',
            'isInventory' => $this->is_inventory ? 'Yes' : 'No',
            'max_ops_job_pick_limit' => $this->max_ops_job_pick_limit,
            'measurement_count' => $this->measurement_count,
            'measurement_unit' => ['id' => $this->measurement_unit, 'name' => $this->measurement_unit],
            'measurement_value' => $this->measurement_value,
            'net_available_qty_pcs_api' => isset($this->net_available_qty_pcs_api) ? $this->net_available_qty_pcs_api : null,
            'not_yet_sync_api_qty' => isset($this->not_yet_sync_api_qty) ? $this->not_yet_sync_api_qty : null,
            'nutri_grade' => $this->nutri_grade,
            'thumbnail' => AttachmentResource::make($this->whenLoaded('thumbnail')),
            'latestUnitCost' => UnitCostResource::make($this->whenLoaded('latestUnitCost')),
            'limit_is_created_by_system' => isset($this->limit_is_created_by_system) ? $this->limit_is_created_by_system : null,
            'needed_qty' => isset($this->needed_qty) ? $this->needed_qty : null,
            'needed_value' => isset($this->needed_value) ? $this->needed_value / 100 : 0,
            'unitCosts' => UnitCostResource::collection($this->whenLoaded('unitCosts')),
            'productLimits' => ProductLimitResource::collection($this->whenLoaded('productLimits')),
            'productUoms' => ProductUomResource::collection($this->whenLoaded('productUoms')),
            'total_movements_qty' => isset($this->total_movements_qty) ? $this->total_movements_qty : 0,
            'total_delivered_qty' => isset($this->total_delivered_qty) ? $this->total_delivered_qty : 0,
            'picked_qty_on_date' => isset($this->picked_qty_on_date) ? $this->picked_qty_on_date : 0,
            'picked_value_on_date' => isset($this->picked_value_on_date) ? $this->picked_value_on_date / 100 : 0,
            'calculated_warehouse_qty' => isset($this->calculated_warehouse_qty) ? $this->calculated_warehouse_qty : null,
            'qty_available_pcs_api' => isset($this->qty_available_pcs_api) ? $this->qty_available_pcs_api : null,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => OperatorResource::make($this->whenLoaded('operator')),
            'sellingPrices' => SellingPriceResource::collection($this->whenLoaded('sellingPrices')),
            'tagBindings' => TagBindingResource::collection($this->whenLoaded('tagBindings')),
            'this_month_count' => $this->this_month_count,
            'this_month_revenue' => $this->this_month_revenue / 100,
            'this_month_gross_profit' => $this->this_month_gross_profit / 100,
            'this_month_gross_profit_margin' => $this->this_month_gross_profit_margin,
            'translated_names_json' => $this->translated_names_json,
            'last_month_count' => $this->last_month_count,
            'last_month_revenue' => $this->last_month_revenue / 100,
            'last_month_gross_profit' => $this->last_month_gross_profit / 100,
            'last_month_gross_profit_margin' => $this->last_month_gross_profit_margin,
            'last_two_month_count' => $this->last_two_month_count,
            'last_two_month_revenue' => $this->last_two_month_revenue / 100,
            'last_two_month_gross_profit' => $this->last_two_month_gross_profit / 100,
            'last_two_month_gross_profit_margin' => $this->last_two_month_gross_profit_margin,
            'vendTransactions' => VendTransactionResource::collection($this->whenLoaded('vendTransactions'))
        ];
    }
}
