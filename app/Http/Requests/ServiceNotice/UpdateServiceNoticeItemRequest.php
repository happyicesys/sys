<?php

namespace App\Http\Requests\ServiceNotice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceNoticeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Clearing the description never deletes the row (the cms autosave
            // did, silently) — an item is removed by its Delete button only.
            'desc' => 'required|string|max:2000',
            'desc_before' => 'nullable|string|max:2000',
            'desc_after' => 'nullable|string|max:2000',
        ];
    }
}
