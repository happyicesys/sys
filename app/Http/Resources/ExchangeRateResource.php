<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
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
            'base_country_id' => $this->base_country_id,
            'quote_country_id' => $this->quote_country_id,
            'rate' => number_format($this->rate / 100, 2, '.', ','),
            'created_at' => Carbon::parse($this->created_at)->toDateString(),
        ];
    }
}
