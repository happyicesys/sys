<?php

namespace App\Http\Requests\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourOverlap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create / edit a Happy Hour campaign. Route permission decides who; this decides what. */
class HappyHourCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'days_mask' => ['required', 'integer', 'min:1', 'max:127'],
            'window_start' => ['required', 'date_format:H:i'],
            'window_end' => ['required', 'date_format:H:i', 'after:window_start'],
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'sku_count' => ['required', 'integer', 'min:1', 'max:30'],
            'selection_rule' => ['required', Rule::in(array_keys(HappyHourCampaign::RULES))],
            'lookback_days' => ['required', 'integer', 'min:1', 'max:90'],
            'discount_pct' => ['required', 'integer', 'min:1', 'max:90'],
            'min_balance_pct' => ['required', 'integer', 'min:0', 'max:99'],
            'min_qty' => ['required', 'integer', 'min:1', 'max:999'],
            'price_step_cents' => ['required', 'integer', Rule::in([1, 5, 10, 50, 100])],
            'allow_below_cost' => ['required', 'boolean'],
            'excluded_product_ids' => ['nullable', 'array'],
            'excluded_product_ids.*' => ['integer', 'exists:products,id'],
            'vend_ids' => ['nullable', 'array'],
            'vend_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'window_end.after' => 'Hours To must be after Hours From (a window cannot run past midnight).',
            'days_mask.min' => 'Pick at least one day.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $vendIds = array_map('intval', (array) $this->input('vend_ids', []));
            $freezers = Vend::query()->whereIn('id', $vendIds)
                ->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)->pluck('id')->all();
            if (count($freezers) !== count($vendIds)) {
                $validator->errors()->add('vend_ids', 'Only smart freezers you can see can take part in Happy Hour.');

                return;
            }
            if (! $this->boolean('is_active')) {
                return;
            }
            foreach (HappyHourOverlap::conflicts($this->validated(), $vendIds, $this->route('id') ? (int) $this->route('id') : null) as $message) {
                $validator->errors()->add('vend_ids', $message);
            }
        }];
    }
}
