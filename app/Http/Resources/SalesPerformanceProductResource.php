<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesPerformanceProductResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,

            'this_month_count' => (int)($this->this_month_count ?? 0),
            'this_month_revenue' => isset($this->this_month_revenue) ? $this->this_month_revenue / 100 : 0,
            'this_month_gross_profit' => isset($this->this_month_gross_profit) ? $this->this_month_gross_profit / 100 : 0,
            'this_month_gross_profit_margin' => isset($this->this_month_gross_profit_margin) ? (float)$this->this_month_gross_profit_margin : 0,
            'this_month_channel_count' => (int)($this->this_month_channel_count ?? 0),
            'this_month_qty_per_day' => isset($this->this_month_qty_per_day) ? (float)$this->this_month_qty_per_day : 0.0,
            'this_month_availability' => $this->this_month_availability !== null ? (float)$this->this_month_availability : null,

            'last_month_count' => (int)($this->last_month_count ?? 0),
            'last_month_revenue' => isset($this->last_month_revenue) ? $this->last_month_revenue / 100 : 0,
            'last_month_gross_profit' => isset($this->last_month_gross_profit) ? $this->last_month_gross_profit / 100 : 0,
            'last_month_gross_profit_margin' => isset($this->last_month_gross_profit_margin) ? (float)$this->last_month_gross_profit_margin : 0,
            'last_month_channel_count' => (int)($this->last_month_channel_count ?? 0),
            'last_month_qty_per_day' => isset($this->last_month_qty_per_day) ? (float)$this->last_month_qty_per_day : 0.0,
            'last_month_availability' => $this->last_month_availability !== null ? (float)$this->last_month_availability : null,

            'last_two_month_count' => (int)($this->last_two_month_count ?? 0),
            'last_two_month_revenue' => isset($this->last_two_month_revenue) ? $this->last_two_month_revenue / 100 : 0,
            'last_two_month_gross_profit' => isset($this->last_two_month_gross_profit) ? $this->last_two_month_gross_profit / 100 : 0,
            'last_two_month_gross_profit_margin' => isset($this->last_two_month_gross_profit_margin) ? (float)$this->last_two_month_gross_profit_margin : 0,
            'last_two_month_channel_count' => (int)($this->last_two_month_channel_count ?? 0),
            'last_two_month_qty_per_day' => isset($this->last_two_month_qty_per_day) ? (float)$this->last_two_month_qty_per_day : 0.0,
            'last_two_month_availability' => $this->last_two_month_availability !== null ? (float)$this->last_two_month_availability : null,
        ];
    }
}
