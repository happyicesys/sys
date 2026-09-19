<?php

namespace App\Http\Requests\ServiceNotice;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission + operator ceiling are enforced by the controller
    }

    public function rules(): array
    {
        return [
            'vend_id' => 'required|integer|exists:vends,id',
            // One line = one thing to fix (the cms batch pattern staff know).
            'items' => 'required|string|max:5000',
            'remarks' => 'nullable|string|max:2000',
            'sequence' => 'nullable|numeric|min:0.1',
        ];
    }

    public function messages(): array
    {
        return [
            'vend_id.required' => 'Choose a machine first.',
            'items.required' => 'Enter at least one service item, 请至少输入一个维修项目',
        ];
    }

    /** @return string[] */
    public function itemLines(): array
    {
        return preg_split('/\R/u', (string) $this->input('items')) ?: [];
    }
}
