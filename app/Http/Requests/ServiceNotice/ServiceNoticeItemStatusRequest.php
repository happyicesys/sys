<?php

namespace App\Http\Requests\ServiceNotice;

use App\Models\ServiceNoticeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceNoticeItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // cms accepted any integer here; only the four real verdicts pass.
            'status' => ['required', 'integer', Rule::in(array_keys(ServiceNoticeItem::STATUS_MAPPINGS))],
            'incomplete_reason' => 'nullable|string|max:1000',
        ];
    }
}
