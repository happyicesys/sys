<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Model; // add this

class StockCountDayGraphResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Is the underlying resource an Eloquent model?
        $isModel = $this->resource instanceof Model;

        return [
            'amount' => isset($this->amount) ? $this->amount : null,
            'coin_float' => isset($this->coin_float) ? $this->coin_float : null,
            'count'  => isset($this->count) ? $this->count : null,
            'date'       => $this->date,
            'day'        => $this->day,
            'month'      => $this->month,
            'month_name' => $this->month_name,
            'year'       => $this->year,

            'machine_qty'   => isset($this->machine_qty) ? (float) $this->machine_qty : null,     // sum of qty_vend
            'warehouse_qty' => isset($this->warehouse_qty) ? (float) $this->warehouse_qty : null,
            'balance_percent' => isset($this->balance_percent) ? (float) $this->balance_percent : null,
        ];
    }
}
