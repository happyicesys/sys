<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTerminalResource;
use App\Http\Resources\CardTerminalUnitResource;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Vend;
use App\Traits\ExportOptimizationTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

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
    use ExportOptimizationTrait;

    public function __construct()
    {
        $this->middleware(['permission:read card-terminals'])->only(['index']);
        $this->middleware(['permission:export card-terminals'])->only(['exportExcel']);
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

        $query = $this->filtered($request, $today)
            ->with([
                'company',
                // Whole fleet by design: a terminal can sit on any machine and
                // this page is HappyIce-staff only, so vend lookups skip the
                // operator scoping the way the old bindings page did.
                'bindings' => fn ($q) => $q->effectiveOn($today)
                    ->with(['vend' => fn ($qq) => $qq->withoutGlobalScopes()->select('id', 'code', 'name')]),
            ])
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
                'vend_code' => $request->input('vend_code', ''),
                'remarks' => $request->input('remarks', ''),
                'sortKey' => $sortKey,
                'sortBy' => $request->sortBy ?? true,
            ],
        ]);
    }

    /**
     * Same rows as the listing, every filter honoured, no pagination.
     *
     * The machine columns come from a terminal_id → machine map built in ONE
     * query rather than an eager load: `cursor()` resolves relations per model,
     * so `with()` here would be a query per row.
     */
    public function exportExcel(Request $request)
    {
        $today = now()->toDateString();
        $sortKey = in_array($request->sortKey, ['terminal_id', 'card_terminal_id'])
            ? $request->sortKey
            : 'terminal_id';
        $sortBy = filter_var($request->sortBy ?? true, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        $machines = $this->currentMachineByTerminal($today);
        $companies = CardTerminal::pluck('name', 'id');

        $query = $this->filtered($request, $today)
            ->orderBy($sortKey, $sortBy)
            ->orderBy('id');

        return (new FastExcel($this->exportWithCursor($query)))->download(
            $this->formatExportFilename('CardTerminals', 'xlsx'),
            function (CardTerminalUnit $unit) use ($machines, $companies) {
                $machine = $machines->get($unit->terminal_id);

                return [
                    'Terminal ID' => $unit->terminal_id,
                    'Card Terminal Company' => $companies[$unit->card_terminal_id] ?? null,
                    'Machine ID' => $machine?->vend_code,
                    'Site' => $machine?->customer_name,
                    'Bound From' => $machine?->bound_from?->format('Y-m-d'),
                    'Remarks' => $unit->remarks,
                ];
            }
        );
    }

    /** Every filter the listing offers, shared with the export. */
    private function filtered(Request $request, string $today): Builder
    {
        return CardTerminalUnit::query()
            ->when($request->input('terminal_id'), fn ($q, $s) => $q->where('terminal_id', 'like', "%{$s}%"))
            ->when($request->input('card_terminal_id'), function ($q, $company) {
                if ($company === 'all') {
                    return $q;
                }

                return $company === 'none'
                    ? $q->whereNull('card_terminal_id')
                    : $q->where('card_terminal_id', $company);
            })
            // Machine filter resolves through the binding effective TODAY, so
            // it answers "which terminal is on machine X now", not "was ever".
            ->when($request->input('vend_code'), function ($q, $search) use ($today) {
                return $q->whereIn('terminal_id', CardTerminalBinding::query()
                    ->effectiveOn($today)
                    ->whereIn('vend_id', Vend::withoutGlobalScopes()
                        ->where('code', 'like', "%{$search}%")
                        ->pluck('id'))
                    ->pluck('terminal_id'));
            })
            ->when($request->input('remarks'), fn ($q, $s) => $q->where('remarks', 'like', "%{$s}%"));
    }

    /**
     * terminal_id → { vend_code, customer_name, bound_from } for the binding in
     * force today. Whole fleet, unscoped, same rationale as the listing.
     */
    private function currentMachineByTerminal(string $today)
    {
        return CardTerminalBinding::query()
            ->effectiveOn($today)
            ->leftJoin('vends', 'vends.id', '=', 'card_terminal_bindings.vend_id')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->orderBy('card_terminal_bindings.id')
            ->get([
                'card_terminal_bindings.terminal_id',
                'card_terminal_bindings.bound_from',
                'vends.code AS vend_code',
                'customers.name AS customer_name',
            ])
            // unique() before keyBy: keyBy would let the LAST row win, while
            // the grid takes the first effective binding. Overlapping ranges
            // for one terminal should not exist, but if they ever do the file
            // and the screen must still say the same thing.
            ->unique('terminal_id')
            ->keyBy('terminal_id');
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
