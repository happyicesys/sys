<?php

namespace App\Http\Requests\StockCheck;

use Illuminate\Foundation\Http\FormRequest;

class SubmitStockCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels' => 'required|array|min:1',
            'channels.*.id' => 'required|integer',
            // Range (0…capacity) and "every line answered" depend on the drawn
            // channels, so StockCheckService::submit owns those checks.
            'channels.*.counted_qty' => 'nullable|integer|min:0',
            'channels.*.note' => 'nullable|string|max:500',
        ];
    }
}
