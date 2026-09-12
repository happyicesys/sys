<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTerminalResource;
use App\Http\Resources\CardTerminalUnitResource;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Customer;
use App\Models\Vend;
use App\Traits\ExportOptimizationTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $today = now()->toDateString();

        $query = $this->sorted($this->filtered($request, $today), $request, $today)
            ->with([
                'company',
                // Whole fleet by design: a terminal can sit on any machine and
                // this page is HappyIce-staff only, so vend lookups skip the
                // operator scoping the way the old bindings page did.
                // The site under the machine comes along for the Machine ID
                // cell's "<site ref id> - <site name>" line; unscoped for the
                // same reason the vend is.
                'bindings' => fn ($q) => $q->effectiveOn($today)
                    ->with(['vend' => fn ($qq) => $qq->withoutGlobalScopes()
                        ->select('id', 'code', 'name', 'customer_id')
                        ->with(['customer' => fn ($c) => $c->withoutGlobalScopes()->select('id', 'name')])]),
            ]);

        $units = $query->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)->withQueryString();
        $this->attachBindingHistory($units->getCollection());

        return Inertia::render('CardTerminalUnit/Index', [
            'cardTerminalUnits' => CardTerminalUnitResource::collection($units),
            'cardTerminalOptions' => CardTerminalResource::collection(CardTerminal::orderBy('name')->get()),
            'filters' => [
                'terminal_id' => $request->input('terminal_id', ''),
                'card_terminal_id' => $request->input('card_terminal_id', 'all'),
                'vend_code' => $request->input('vend_code', ''),
                'is_bound' => $request->input('is_bound', 'all'),
                'will_auto_refund' => $request->input('will_auto_refund', 'all'),
                'batch' => $request->input('batch', ''),
                'remarks' => $request->input('remarks', ''),
                'sortKey' => $this->sortKey($request),
                'sortBy' => $request->sortBy ?? true,
            ],
        ]);
    }

    /**
     * The last three machines each listed terminal sat on, newest first, hung
     * on the model as `binding_history` for CardTerminalUnitResource.
     *
     * One extra query for the whole page rather than a per-row limit: a page is
     * at most a few hundred bindings, and the eager load above is already
     * narrowed to TODAY's binding, which is the only one the row itself shows.
     *
     * @param  \Illuminate\Support\Collection<int, CardTerminalUnit>  $units
     */
    private function attachBindingHistory($units): void
    {
        $terminalIds = $units->pluck('terminal_id')->filter()->unique();
        if ($terminalIds->isEmpty()) {
            return;
        }

        $byTerminal = CardTerminalBinding::query()
            ->whereIn('terminal_id', $terminalIds)
            ->with([
                'creator:id,name',
                // Fleet-wide like the rest of this page: a terminal's history
                // may run through machines of another operator.
                'vend' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'code'),
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('terminal_id');

        foreach ($units as $unit) {
            $unit->setAttribute('binding_history', ($byTerminal[$unit->terminal_id] ?? collect())
                ->take(3)
                ->map(fn (CardTerminalBinding $row) => [
                    'vend_code' => $row->vend?->code,
                    'bound_from' => $row->bound_from?->format('Y-m-d'),
                    'bound_until' => $row->bound_until?->format('Y-m-d'),
                    'bound_at' => $row->created_at?->toIso8601String(),
                    'bound_by' => $row->boundByLabel(),
                ])->values()->all());
        }
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

        $machines = $this->currentMachineByTerminal($today);
        $companies = CardTerminal::pluck('name', 'id');

        $query = $this->sorted($this->filtered($request, $today), $request, $today);

        return (new FastExcel($this->exportWithCursor($query)))->download(
            $this->formatExportFilename('CardTerminals', 'xlsx'),
            function (CardTerminalUnit $unit) use ($machines, $companies) {
                $machine = $machines->get($unit->terminal_id);

                return [
                    'Terminal ID' => $unit->terminal_id,
                    'Auresys Terminal ID' => $unit->auresys_terminal_id,
                    'Card Terminal Company' => $companies[$unit->card_terminal_id] ?? null,
                    'Machine ID' => $machine?->vend_code,
                    // Site ID# as the rest of mark1 shows it, not the raw key.
                    'Site ID' => $machine?->customer_id ? $machine->customer_id + Customer::RUNNING_NUMBER_INIT : null,
                    'Site' => $machine?->customer_name,
                    'Bound From' => $machine?->bound_from?->format('Y-m-d'),
                    // When the binding was RECORDED, matching the listing's
                    // "bound …" line; bound_from above is what it covers.
                    'Bound At' => $machine?->bound_at ? Carbon::parse($machine->bound_at)->format('ymd h:i a') : null,
                    'Batch' => $unit->batch,
                    'Auto Refund?' => match ($unit->willAutoRefund()) {
                        true => 'Yes',
                        false => 'No',
                        default => 'Unknown',
                    },
                    'Remarks' => $unit->remarks,
                ];
            }
        );
    }

    /** Whitelisted sort column. `vend_code` sorts through the binding. */
    private function sortKey(Request $request): string
    {
        return in_array($request->sortKey, ['terminal_id', 'card_terminal_id', 'vend_code', 'batch', 'is_will_auto_refund'])
            ? $request->sortKey
            : 'terminal_id';
    }

    /**
     * Ordering, shared by the listing and the export so the file comes out in
     * the order on screen.
     *
     * Machine ID is not a column on this table — it comes from the binding in
     * force today — so sorting by it orders on a correlated subquery rather
     * than a join, which would multiply rows for a terminal with more than one
     * binding. Terminals on no machine sort last either way (NULL is forced to
     * the bottom), so "sort by machine" never buries the bound ones.
     */
    private function sorted(Builder $query, Request $request, string $today): Builder
    {
        $sortKey = $this->sortKey($request);
        $direction = filter_var($request->sortBy ?? true, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        if ($sortKey !== 'vend_code') {
            return $query->orderBy($sortKey, $direction)->orderBy('id');
        }

        $code = $this->currentVendCodeSubquery($today);

        return $query
            ->orderByRaw('('.$code->toSql().') IS NULL', $code->getBindings())
            ->orderBy($code, $direction)
            ->orderBy('id');
    }

    /**
     * The machine this terminal sits on today, correlated to the outer
     * `card_terminal_units` row.
     *
     * Both callers below build on this, and it goes through
     * CardTerminalBinding::effectiveOn() rather than re-spelling the date
     * predicate. That rule is what settlement matching resolves a report line
     * on; a second hand-written copy of it here could silently drift from the
     * one CardSettlementMatcher uses.
     *
     * Joins `vends` so a binding pointing at a machine that no longer exists
     * resolves to nothing — which is exactly what the Machine ID column shows
     * for such a row, so the column, the filter and the sort always agree.
     * Unscoped by design: staff-only page, whole fleet, as everywhere here.
     */
    private function currentMachineSubquery(string $today)
    {
        return CardTerminalBinding::query()
            ->join('vends', 'vends.id', '=', 'card_terminal_bindings.vend_id')
            ->whereColumn('card_terminal_bindings.terminal_id', 'card_terminal_units.terminal_id')
            ->effectiveOn($today);
    }

    /** That machine's code, for ordering. Oldest binding wins, as the grid does. */
    private function currentVendCodeSubquery(string $today)
    {
        return $this->currentMachineSubquery($today)
            ->select('vends.code')
            ->orderBy('card_terminal_bindings.id')
            ->limit(1);
    }

    /**
     * "Is this terminal on a machine today?" — the same question the Machine ID
     * column answers, so the filter and the column can never disagree.
     */
    private function boundToMachine(string $today)
    {
        return $this->currentMachineSubquery($today)->select(DB::raw(1));
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
            // Yes = on a machine today, No = spare on the shelf.
            ->when($request->input('is_bound'), function ($q, $value) use ($today) {
                if (! in_array($value, ['yes', 'no'], true)) {
                    return $q;
                }

                return $value === 'yes'
                    ? $q->whereExists($this->boundToMachine($today))
                    : $q->whereNotExists($this->boundToMachine($today));
            })
            // Yes / No / Unknown — the tri-state the reconciler reads.
            ->when($request->input('will_auto_refund'), function ($q, $value) {
                return match ($value) {
                    'yes' => $q->where('is_will_auto_refund', 1),
                    'no' => $q->where('is_will_auto_refund', 0),
                    'unknown' => $q->whereNull('is_will_auto_refund'),
                    default => $q,
                };
            })
            // Free text, like Terminal ID and Remarks: the batch names are
            // human strings ("Nets #3 (50x)", "Auresys #2 (15x)"), so a
            // substring search on "auresys" has to pull the whole series.
            ->when($request->input('batch'), fn ($q, $s) => $q->where('batch', 'like', "%{$s}%"))
            ->when($request->input('remarks'), fn ($q, $s) => $q->where('remarks', 'like', "%{$s}%"));
    }

    /**
     * terminal_id → { vend_code, customer_id, customer_name, bound_from,
     * bound_at } for the binding in force today. Whole fleet, unscoped, same rationale as the listing.
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
                'card_terminal_bindings.created_at AS bound_at',
                'vends.code AS vend_code',
                'customers.id AS customer_id',
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
            // Auresys' own EZ terminal ID. Digits only — every one seen is
            // numeric — but NOT unique: a duplicate has to stay saveable so it
            // can be corrected on the page.
            'auresys_terminal_id' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'batch' => ['nullable', 'string', 'max:32'],
            // 'auto' = no override (seed / unknown), 'yes' / 'no' = a manual flag
            // that the seed import will not overwrite.
            'will_auto_refund' => ['nullable', Rule::in(['auto', 'yes', 'no'])],
        ]);

        $attrs = [
            'terminal_id' => trim($validated['terminal_id']),
            'card_terminal_id' => $validated['card_terminal_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            // Blank clears it: a non-Auresys unit has no EZ TID at all, so an
            // empty box must mean null rather than "".
            'auresys_terminal_id' => trim((string) ($validated['auresys_terminal_id'] ?? '')) ?: null,
        ];
        if (array_key_exists('batch', $validated)) {
            $attrs['batch'] = $validated['batch'] !== null && $validated['batch'] !== '' ? trim($validated['batch']) : null;
        }
        if (! empty($validated['will_auto_refund'])) {
            $unit = $ignoreId ? CardTerminalUnit::find($ignoreId) : null;
            if ($validated['will_auto_refund'] !== 'auto') {
                $attrs['is_will_auto_refund'] = $validated['will_auto_refund'] === 'yes' ? 1 : 0;
                $attrs['auto_refund_flag_source'] = CardTerminalUnit::FLAG_SOURCE_MANUAL;
            } elseif ($unit && $unit->auto_refund_flag_source === CardTerminalUnit::FLAG_SOURCE_MANUAL) {
                // Override lifted: back to the workbook's answer if it had one, else unknown.
                $seedFlag = $unit->auto_refund_stats_json['seed_flag'] ?? null;
                $attrs['is_will_auto_refund'] = $seedFlag;
                $attrs['auto_refund_flag_source'] = $seedFlag === null ? null : CardTerminalUnit::FLAG_SOURCE_SEED;
            }
        }

        return $attrs;
    }
}
