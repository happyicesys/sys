<?php

namespace App\Http\Requests\Grab;

/**
 * One or more stores' price rule: the company rule (use_company_rule), or their own rule and a note saying why
 * they are priced differently.
 */
class StorePriceRuleRequest extends GrabPriceRuleRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $own = ! $this->boolean('use_company_rule');

        return [
            'use_company_rule' => ['required', 'boolean'],
            'ref_ids' => ['required', 'array', 'min:1'],
            'ref_ids.*' => ['integer', 'distinct'],
            'note' => ['nullable', 'string', 'max:255'],
            ...($own ? self::ruleFields() : []),
        ];
    }
}
