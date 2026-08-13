<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApkSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authorization is a separate, tracked change (the
        // /apk-settings group carries no `can:` middleware today); this
        // request only adds input validation.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
