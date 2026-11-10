<?php

namespace App\Http\Requests\Grab;

/** The operator's (company) price rule, and the Grab category every sub-category sits under. */
class CompanyPriceRuleRequest extends GrabPriceRuleRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...self::ruleFields(),
            'category' => ['nullable', 'array'],
            'category.id' => ['required_with:category', 'string', 'max:64'],
            'category.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
