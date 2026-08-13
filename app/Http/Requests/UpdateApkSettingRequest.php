<?php

namespace App\Http\Requests;

use App\ValueObjects\ApkSettingParameters;

/**
 * Validation for /apk-settings/{id}/update.
 *
 * Extends the store request so name/remarks rules stay identical between
 * create and edit. Parameter rules are generated from the ApkSettingParameters
 * registry, so a key added to SCHEMA is validated with no change here. Rules
 * are deliberately permissive enough for everything the current Edit.vue
 * legitimately posts ('true'/'false' strings from selects, numeric strings
 * from number inputs, null-cleared dates); normalization to the canonical
 * wire shape happens in the value object, not here.
 */
class UpdateApkSettingRequest extends StoreApkSettingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), ApkSettingParameters::validationRules(), [
            'vends' => ['nullable', 'array'],
            'vends.*' => ['integer', 'exists:vends,id'],
        ]);
    }
}
