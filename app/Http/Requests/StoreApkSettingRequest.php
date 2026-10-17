<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApkSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is ApkSettingController's per-verb permission middleware
        // (`create` / `update apk-settings`); this request only adds input validation.
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
