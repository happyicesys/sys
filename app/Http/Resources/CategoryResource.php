<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'category_group_id' => CategoryGroupResource::make($this->whenLoaded('categoryGroup')),
            'classname' => $this->classname,
            'desc' => $this->desc,
            'name' => $this->name,
            'remarks' => $this->remarks,
            'sequence' => $this->sequence,
            'type' => $this->type,
            'categoryGroup' => CategoryGroupResource::make($this->whenLoaded('categoryGroup')),
            'this_month_revenue' => $this->this_month_revenue/100,
            'this_month_gross_profit' => $this->this_month_gross_profit/100,
            'this_month_gross_profit_margin' => $this->this_month_gross_profit_margin,
            'last_month_revenue' => $this->last_month_revenue/100,
            'last_month_gross_profit' => $this->last_month_gross_profit/100,
            'last_month_gross_profit_margin' => $this->last_month_gross_profit_margin,
            'last_two_month_revenue' => $this->last_two_month_revenue/100,
            'last_two_month_gross_profit' => $this->last_two_month_gross_profit/100,
            'last_two_month_gross_profit_margin' => $this->last_two_month_gross_profit_margin,
        ];
    }
}
