<?php

namespace App\Http\Requests\StockCheck;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission + operator ceiling are enforced by the controller
    }

    public function rules(): array
    {
        return [
            'vend_id' => 'required|integer|exists:vends,id',
            // The "Random" checkbox: off = every stocked channel, on = draw sample_size of them.
            'is_random' => 'required|boolean',
            'sample_size' => 'nullable|required_if:is_random,true,1|integer|min:1|max:500',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'remarks' => 'nullable|string|max:2000',
            'sequence' => 'nullable|numeric|min:0.1',
        ];
    }

    public function messages(): array
    {
        return [
            'vend_id.required' => 'Choose a machine first.',
            'sample_size.required_if' => 'Say how many channels to draw.',
        ];
    }
}
