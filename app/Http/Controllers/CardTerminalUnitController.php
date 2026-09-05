<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTerminalResource;
use App\Http\Resources\CardTerminalUnitResource;
use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Data Management → Card Terminal. CRUD over the physical terminal units:
 * acquirer terminal ID paired with the supplying company (`card_terminals`,
 * shown as "Card Terminal Company").
 *
 * Deliberately no machine binding here — a terminal is put on a machine from
 * that machine's Setting/Edit page, which owns the effective-dated
 * `card_terminal_bindings` rows the settlement matcher reads. The machine
 * column on the listing is read-only context.
 *
 * Shares the `card-terminals` permission with CardTerminalController: same
 * Data Management concern, same audience, and no seeder run needed on deploy.
 */
class CardTerminalUnitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read card-terminals'])->only(['index']);
        $this->middleware(['permission:create card-terminals'])->only(['create']);
        $this->middleware(['permission:update card-terminals'])->only(['update']);
        $this->middleware(['permission:delete card-terminals'])->only(['delete']);
    }

    public function index(Request $request)
    {
        $request->validate(['numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/']]);

        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = in_array($request->sortKey, ['terminal_id', 'card_terminal_id'])
            ? $request->sortKey
            : 'terminal_id';
        $sortBy = filter_var($request->sortBy ?? true, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        $today = now()->toDateString();

        $query = CardTerminalUnit::query()
            ->with([
                'company',
                // Whole fleet by design: a terminal can sit on any machine and
                // this page is HappyIce-staff only, so vend lookups skip the
                // operator scoping the way the old bindings page did.
                'bindings' => fn ($q) => $q->effectiveOn($today)
                    ->with(['vend' => fn ($qq) => $qq->withoutGlobalScopes()->select('id', 'code', 'name')]),
            ])
            ->when($request->input('terminal_id'), fn ($q, $s) => $q->where('terminal_id', 'like', "%{$s}%"))
            ->when($request->input('card_terminal_id'), function ($q, $company) {
                if ($company === 'all') {
                    return $q;
                }

                return $company === 'none'
                    ? $q->whereNull('card_terminal_id')
                    : $q->where('card_terminal_id', $company);
            })
            ->when($request->input('remarks'), fn ($q, $s) => $q->where('remarks', 'like', "%{$s}%"))
            ->orderBy($sortKey, $sortBy)
            ->orderBy('id');

        return Inertia::render('CardTerminalUnit/Index', [
            'cardTerminalUnits' => CardTerminalUnitResource::collection(
                $query->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)->withQueryString()
            ),
            'cardTerminalOptions' => CardTerminalResource::collection(CardTerminal::orderBy('name')->get()),
            'filters' => [
                'terminal_id' => $request->input('terminal_id', ''),
                'card_terminal_id' => $request->input('card_terminal_id', 'all'),
                'remarks' => $request->input('remarks', ''),
                'sortKey' => $sortKey,
                'sortBy' => $request->sortBy ?? true,
            ],
        ]);
    }

    public function create(Request $request)
    {
        CardTerminalUnit::create($this->validated($request));

        return redirect()->route('card-terminal-units');
    }

    public function update(Request $request, $id)
    {
        $unit = CardTerminalUnit::findOrFail($id);
        $unit->update($this->validated($request, $unit->id));

        return redirect()->route('card-terminal-units');
    }

    public function delete($id)
    {
        CardTerminalUnit::findOrFail($id)->delete();

        return redirect()->route('card-terminal-units');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        // terminal_id is unique fleet-wide, not per company: settlement
        // matching resolves a report row by TID alone, so the same string
        // under two companies would resolve to a machine arbitrarily.
        $validated = $request->validate([
            'terminal_id' => [
                'required', 'string', 'max:64',
                Rule::unique('card_terminal_units', 'terminal_id')->ignore($ignoreId),
            ],
            'card_terminal_id' => ['nullable', 'integer', Rule::exists('card_terminals', 'id')],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'terminal_id' => trim($validated['terminal_id']),
            'card_terminal_id' => $validated['card_terminal_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }
}
