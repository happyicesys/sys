<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTerminalBindingResource;
use App\Models\CardTerminalBinding;
use App\Models\Vend;
use App\Services\CardSettlement\ParserRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Acquirer terminal (e.g. NETS TID) → machine bindings. Effective-dated so a
 * terminal can move between machines without breaking historical settlement
 * matching. Bulk seeding from the acquirer's binding sheet goes through
 * `php artisan card-settlement:import-bindings`.
 *
 * The page is HappyIce-staff only (permission `card-settlements`) and shows
 * the whole fleet by design — the NETS merchant account is company-wide — so
 * vend lookups deliberately run withoutGlobalScopes.
 */
class CardTerminalBindingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read card-settlements'])->only(['index']);
        $this->middleware(['permission:update card-settlements'])->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->validate(['numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/']]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = in_array($request->sortKey, ['terminal_id', 'provider', 'bound_from', 'bound_until'])
            ? $request->sortKey
            : 'terminal_id';
        $sortBy = filter_var($request->sortBy ?? true, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        $query = CardTerminalBinding::query()
            ->with(['vend' => fn ($q) => $q->withoutGlobalScopes()
                ->select('id', 'code', 'name', 'customer_id')
                ->with(['customer' => fn ($qq) => $qq->withoutGlobalScopes()->select('id', 'name')]),
            ])
            ->when($request->input('terminal_id'), fn ($q, $s) => $q->where('terminal_id', 'like', "%{$s}%"))
            ->when($request->input('vend_code'), fn ($q, $s) => $q->whereIn(
                'vend_id',
                Vend::withoutGlobalScopes()->where('code', 'like', "%{$s}%")->pluck('id')
            ))
            ->when($request->input('provider'), fn ($q, $p) => $p !== 'all' ? $q->where('provider', $p) : $q)
            ->when($request->boolean('active_only', true), fn ($q) => $q->effectiveOn(now()->toDateString()))
            ->orderBy($sortKey, $sortBy)
            ->orderBy('id');

        return Inertia::render('CardTerminalBinding/Index', [
            'bindings' => CardTerminalBindingResource::collection(
                $query->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)->withQueryString()
            ),
            'providers' => ParserRegistry::providers(),
            // Machine picker options for the Form modal — whole active fleet,
            // labelled like the Simcard form's picker. Disposed machines that
            // still carry a binding stay listed, or their historical bindings
            // could never be edited (the picker would resolve vend_id to null).
            'vends' => Vend::withoutGlobalScopes()
                ->where(fn ($q) => $q
                    ->where('is_disposed', false)
                    ->orWhereIn('id', CardTerminalBinding::query()->select('vend_id')))
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'filters' => [
                'terminal_id' => $request->input('terminal_id', ''),
                'vend_code' => $request->input('vend_code', ''),
                'provider' => $request->input('provider', 'all'),
                'active_only' => $request->boolean('active_only', true),
                'sortKey' => $sortKey,
                'sortBy' => $request->sortBy ?? true,
            ],
        ]);
    }

    public function store(Request $request)
    {
        CardTerminalBinding::create($this->validated($request));

        return back()->with('message', 'Binding created.');
    }

    public function update(Request $request, $id)
    {
        $binding = CardTerminalBinding::findOrFail($id);
        $binding->update($this->validated($request, $binding->id));

        return back()->with('message', 'Binding updated.');
    }

    public function destroy($id)
    {
        CardTerminalBinding::findOrFail($id)->delete();

        return back()->with('message', 'Binding deleted.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:20'],
            'terminal_id' => ['required', 'string', 'max:64'],
            'vend_id' => ['required', 'integer'],
            'bound_from' => ['nullable', 'date'],
            'bound_until' => ['nullable', 'date', 'after_or_equal:bound_from'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        // ValidationException (not abort 422) so the Inertia form renders the
        // message inline instead of the generic error modal.
        $vend = Vend::withoutGlobalScopes()->find($validated['vend_id']);
        if (! $vend) {
            throw ValidationException::withMessages(['vend_id' => 'Unknown machine.']);
        }

        // Refuse a second OPEN-ENDED binding for the same terminal: two
        // concurrent open bindings would make matching pick arbitrarily.
        if (empty($validated['bound_until'])) {
            $clash = CardTerminalBinding::query()
                ->where('provider', $validated['provider'])
                ->where('terminal_id', $validated['terminal_id'])
                ->whereNull('bound_until')
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($clash) {
                throw ValidationException::withMessages([
                    'terminal_id' => 'This terminal already has an open-ended binding — close it (set Bound Until) first.',
                ]);
            }
        }

        return [
            'provider' => $validated['provider'],
            'terminal_id' => $validated['terminal_id'],
            'vend_id' => $vend->id,
            'bound_from' => $validated['bound_from'] ?? null,
            'bound_until' => $validated['bound_until'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }
}
