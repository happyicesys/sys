<?php

namespace App\Http\Requests\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourEligibility;
use App\Services\HappyHour\HappyHourMachineGroups;
use App\Services\HappyHour\HappyHourOverlap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            // How many of them are on offer together in one slot.
            'skus_per_slot' => ['required', 'integer', 'min:1', 'max:10', 'lte:sku_count'],
            'selection_rule' => ['required', Rule::in(array_keys(HappyHourCampaign::RULES))],
            'lookback_days' => ['required', 'integer', 'min:1', 'max:90'],
            'discount_pct' => ['required', 'integer', 'min:1', 'max:90'],
            'min_balance_pct' => ['required', 'integer', 'min:0', 'max:99'],
            'min_qty' => ['required', 'integer', 'min:1', 'max:999'],
            'price_step_cents' => ['required', 'integer', Rule::in([1, 5, 10, 50, 100])],
            'allow_below_cost' => ['required', 'boolean'],
            // Absent = keep the stored value (on by default).
            'skip_unavailable' => ['sometimes', 'boolean'],
            // Bestseller hold: for X hours after a Stock In, the top Y sellers are not discounted. 0 = off.
            // Absent (an old tab) = keep the stored value.
            'bestseller_hold_hours' => ['sometimes', 'integer', 'min:0', 'max:168'],
            'bestseller_hold_count' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'bestseller_lookback_days' => ['sometimes', 'integer', Rule::in(HappyHourCampaign::BESTSELLER_LOOKBACK_DAYS)],
            'excluded_product_ids' => ['nullable', 'array'],
            'excluded_product_ids.*' => ['integer', 'exists:products,id'],
            // Prioritize: offered first in every other slot (HappyHourPlanner::isPriorityTime).
            'priority_product_ids' => ['nullable', 'array', 'max:30'],
            'priority_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'vend_ids' => ['nullable', 'array'],
            'vend_ids.*' => ['integer', 'distinct'],
            // Live groups (HappyHourMachineGroups): the owner's machines of that kind, joining as they qualify.
            'machine_groups' => ['nullable', 'array'],
            'machine_groups.*' => ['string', 'distinct', Rule::in(array_keys(HappyHourMachineGroups::GROUPS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'window_end.after' => 'Hours To must be after Hours From (a window cannot run past midnight).',
            'days_mask.min' => 'Pick at least one day.',
            'skus_per_slot.lte' => 'SKUs at the same time cannot be more than the SKUs taking turns.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $both = array_intersect(
                array_map('intval', (array) $this->input('priority_product_ids', [])),
                array_map('intval', (array) $this->input('excluded_product_ids', [])),
            );
            if ($both !== []) {
                $names = DB::table('products')->whereIn('id', $both)->orderBy('code')->pluck('code')->implode(', ');
                $validator->errors()->add('priority_product_ids', "A SKU cannot be both prioritized and never discounted: {$names}.");

                return;
            }
            $vendIds = array_map('intval', (array) $this->input('vend_ids', []));
            if (count($this->allowedVendIds($vendIds)) !== count($vendIds)) {
                $validator->errors()->add('vend_ids', sprintf(
                    'Only smart freezers and touchscreen vending machines (big board, app %d+) you can see can take part in Happy Hour.',
                    HappyHourEligibility::minVendingApk(),
                ));

                return;
            }
            if (! $this->boolean('is_active')) {
                return;
            }
            // Read once: validated() throws as soon as an error has been added below.
            $data = $this->validated();
            $ignoreId = $this->route('id') ? (int) $this->route('id') : null;
            $groups = array_values((array) $this->input('machine_groups', []));
            $operatorId = $this->ownerOperatorId();
            // Machines picked by name clash only with another campaign that picked them by name too; a
            // group never clashes with a pick (the pick wins) — only with the same group elsewhere.
            foreach (HappyHourOverlap::conflicts($data, $vendIds, $ignoreId) as $message) {
                $validator->errors()->add('vend_ids', $message);
            }
            foreach (HappyHourOverlap::groupConflicts($data, $groups, $operatorId, $ignoreId) as $message) {
                $validator->errors()->add('machine_groups', $message);
            }
        }];
    }

    /** Whose machines a live group covers: the campaign's owner (OperatorHappyHourCampaignScope). */
    private function ownerOperatorId(): int
    {
        $owner = $this->route('id') ? HappyHourCampaign::query()->whereKey((int) $this->route('id'))->value('operator_id') : null;

        return (int) ($owner ?: auth()->user()?->operator_id ?: OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    /**
     * The requested machines that may be in a campaign, among those the viewer can see (Vend's
     * operator scope): any smart freezer (an old app only waits for slots), a vending machine that
     * can run Happy Hour (HappyHourEligibility), or one already in THIS campaign — an edit must not
     * fail because a bound machine went inactive. A paused machine (live UI Setting campaign) is
     * allowed: the page warns, it never blocks.
     *
     * @param  list<int>  $vendIds
     * @return list<int>
     */
    private function allowedVendIds(array $vendIds): array
    {
        if ($vendIds === []) {
            return [];
        }
        $bound = $this->route('id')
            ? DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', (int) $this->route('id'))
                ->pluck('vend_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return Vend::query()->whereIn('id', $vendIds)
            ->whereIn('machine_type', [Vend::MACHINE_TYPE_SMART_FREEZER, Vend::MACHINE_TYPE_VENDING_MACHINE])
            ->get(['id', 'machine_type', 'is_active', 'apk_version_code', 'apk_ver_json'])
            ->filter(fn (Vend $v) => $v->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER
                || HappyHourEligibility::supportsVending($v)
                || in_array($v->id, $bound, true))
            ->pluck('id')->all();
    }
}
