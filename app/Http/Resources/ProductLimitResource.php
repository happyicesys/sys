<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductLimitResource extends JsonResource
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
            'created_by' => $this->created_by,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'date' => $this->date,
            'is_created_by_system' => $this->is_created_by_system ? true : false,
            'product_id' => $this->product_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'qty' => $this->qty,
            'setup_date' => $this->setup_date,
            'setupDate' => Carbon::parse($this->setup_date)->format('ymd'),
            'created_at' => $this->created_at,
            'createdAt' => Carbon::parse($this->created_at)->format('ymd h:ia'),
            'updated_at' => $this->updated_at,
        ];
    }
}
