<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendTransactionGraphResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'amount' => $this->amount/ 100,
            'count' => $this->count,
            'date' => $this->date,
            'day' => $this->day,
            'month' => $this->month,
            'month_name' => $this->month_name,
        ];
    }
}
