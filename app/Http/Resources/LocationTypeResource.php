<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'name' => $this->name,
            'remarks' => $this->remarks,
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
