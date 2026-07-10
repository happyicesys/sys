<?php

namespace App\Http\Controllers;

use App\Http\Resources\PayoutGroupResource;
use App\Models\Operator;
use App\Models\PayoutGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin CRUD for "Operator Groups" (payout groups): a set of operators that share
 * ONE originating CIMB account for refund settlements. Managed here so the shared
 * bank account and membership are data-driven instead of hardcoded.
 */
class PayoutGroupController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ?: 100,
            'sortKey' => $request->sortKey ?: 'code',
            'sortBy' => $request->sortBy ?? true,
        ]);

        return Inertia::render('OperatorGroup/Index', [
            'groups' => PayoutGroupResource::collection(
                PayoutGroup::query()
                    ->with(['operators' => fn ($q) => $q->orderBy('name')])
                    ->when($request->name, fn ($q, $s) => $q->where(fn ($w) => $w
                        ->where('name', 'like', "%{$s}%")
                        ->orWhere('code', 'like', "%{$s}%")))
                    ->when($request->sortKey, fn ($q, $s) => $q->orderBy($s, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            // Every operator (scopes off — admin context), for the assignment picker.
            'operatorOptions' => Operator::withoutGlobalScopes()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'payout_group_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, null);
        $group = PayoutGroup::create($this->attributes($data));
        $this->syncOperators($group, $data['operator_ids'] ?? []);

        return redirect()->route('operator-groups');
    }

    public function update(Request $request, $id)
    {
        $group = PayoutGroup::findOrFail($id);
        $data = $this->validateData($request, $group->id);
        $group->update($this->attributes($data));
        $this->syncOperators($group, $data['operator_ids'] ?? []);

        return redirect()->route('operator-groups');
    }

    public function delete($id)
    {
        $group = PayoutGroup::findOrFail($id);
        // Release members so they fall back to their own operator bank account.
        Operator::withoutGlobalScopes()->where('payout_group_id', $group->id)->update(['payout_group_id' => null]);
        $group->delete();

        return redirect()->route('operator-groups');
    }

    protected function validateData(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('payout_groups', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'bank_account_no' => ['nullable', 'string', 'max:34'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'operator_ids' => ['nullable', 'array'],
            'operator_ids.*' => ['integer'],
        ]);
    }

    protected function attributes(array $data): array
    {
        return [
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    /** Assign the selected operators to this group; release any that were removed. */
    protected function syncOperators(PayoutGroup $group, array $operatorIds): void
    {
        $operatorIds = array_values(array_unique(array_map('intval', $operatorIds)));

        Operator::withoutGlobalScopes()
            ->where('payout_group_id', $group->id)
            ->when(!empty($operatorIds), fn ($q) => $q->whereNotIn('id', $operatorIds))
            ->update(['payout_group_id' => null]);

        if (!empty($operatorIds)) {
            Operator::withoutGlobalScopes()
                ->whereIn('id', $operatorIds)
                ->update(['payout_group_id' => $group->id]);
        }
    }
}
