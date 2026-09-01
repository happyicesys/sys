<?php

namespace App\Http\Controllers;

use App\Models\CardTerminalBinding;
use App\Models\Vend;
use App\Services\CardSettlement\ParserRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Acquirer terminal (e.g. NETS TID) → machine bindings. Effective-dated so a
 * terminal can move between machines without breaking historical settlement
 * matching. Bulk seeding from the acquirer's binding sheet goes through
 * `php artisan card-settlement:import-bindings`.
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
        $query = CardTerminalBinding::query()
            ->with('vend:id,code,name')
            ->when($request->input('provider'), fn ($q, $p) => $p !== 'all' ? $q->where('provider', $p) : $q)
            ->when($request->input('search'), function ($q, $s) {
                $q->where(fn ($qq) => $qq
                    ->where('terminal_id', 'like', "%{$s}%")
                    ->orWhereIn('vend_id', Vend::withoutGlobalScopes()->where('code', 'like', "%{$s}%")->pluck('id')));
            })
            ->when($request->boolean('active_only', true), fn ($q) => $q->effectiveOn(now()->toDateString()))
            ->orderBy('terminal_id');

        $page = $query->paginate(50)->withQueryString();

        return Inertia::render('CardTerminalBinding/Index', [
            'bindings' => $page->through(fn (CardTerminalBinding $b) => [
                'id' => $b->id,
                'provider' => $b->provider,
                'terminal_id' => $b->terminal_id,
                'vend_id' => $b->vend_id,
                'vend_code' => $b->vend?->code,
                'vend_name' => $b->vend?->name,
                'bound_from' => $b->bound_from?->format('Y-m-d'),
                'bound_until' => $b->bound_until?->format('Y-m-d'),
                'remarks' => $b->remarks,
            ]),
            'providers' => ParserRegistry::providers(),
            'filters' => [
                'provider' => $request->input('provider', 'all'),
                'search' => $request->input('search', ''),
                'active_only' => $request->boolean('active_only', true),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        CardTerminalBinding::create($validated);

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
            'vend_code' => ['required', 'integer'],
            'bound_from' => ['nullable', 'date'],
            'bound_until' => ['nullable', 'date', 'after_or_equal:bound_from'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $vend = Vend::withoutGlobalScopes()->where('code', $validated['vend_code'])->first();
        if (! $vend) {
            abort(422, "No machine with code {$validated['vend_code']}.");
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
                abort(422, 'This terminal already has an open-ended binding — close it (set Bound Until) first.');
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
