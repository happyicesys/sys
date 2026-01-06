<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Model; // add this

class VendTransactionGraphResource extends JsonResource
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
            // ❗ If your new dayGraphData already uses RM values, keep as-is.
            // If you still feed cents elsewhere, division by 100 remains correct.
            'amount' => $this->amount / 100,
            'count' => $this->count,

            // Only include relationships when it's an Eloquent model with the relation loaded
            'customer' => $this->when(
                $this->resource->relationLoaded('customer'),
                fn() => CustomerResource::make($this->resource->customer)
            ),
            'product' => $this->when(
                $this->resource->relationLoaded('product'),
                fn() => ProductResource::make($this->resource->product)
            ),
            'vend' => $this->when(
                $isModel && $this->resource->relationLoaded('vend'),
                fn() => VendResource::make($this->resource->vend)
            ),

            'date' => $this->date,
            'day' => $this->day,
            'month' => $this->month,
            'month_name' => $this->month_name,
            'year' => $this->year,
            'weather_icon' => $this->weather_icon ?? null,
        ];
    }
}
