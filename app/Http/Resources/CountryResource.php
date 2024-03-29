<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'currency_name' => $this->currency_name,
            'currency_exponent' => $this->currency_exponent,
            'currency_symbol' => $this->currency_symbol,
            'is_currency_exponent_hidden' => $this->is_currency_exponent_hidden,
            'phone_code' => $this->phone_code,
            'latestQuoteExchangeRate' => ExchangeRateResource::make($this->whenLoaded('latestQuoteExchangeRate')),
            'quoteExchangeRates' => ExchangeRateResource::collection($this->whenLoaded('quoteExchangeRates')),
        ];
    }
}
