<?php

namespace App\Http\Controllers;

use App\Http\Resources\TelcoResource;
use App\Models\Simcard;
use App\Models\Telco;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TelcoController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        // Status filter: 'active' (default) | 'inactive' | 'all' — same shape
        // and defaults as the Operator index, which retires rows the same way.
        $status = $request->status ?: 'active';

        return Inertia::render('Telco/Index', [
            'telcos' => TelcoResource::collection(
                Telco::query()
                    // "Active / total" column. `simcards_on_machine_count` is
                    // the number that matters: a SIM bound to a vend is the
                    // one thing that blocks retiring the package, and
                    // simcards.is_active is 1 for every SIM in prod, so an
                    // is_active-based count would read N/N on every row.
                    ->withCount([
                        'simcards',
                        'simcards as simcards_on_machine_count' => function ($query) {
                            $query->whereHas('vends');
                        },
                    ])
                    ->when($status === 'active', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->when($status === 'inactive', function ($query) {
                        $query->where('is_active', false);
                    })
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'status' => $status,
            'colorOptions' => Telco::COLORS,
            'usageProviderOptions' => $this->usageProviderOptions(),
        ]);
    }

    public function create(Request $request)
    {
        Telco::create($this->validated($request));

        return redirect()->route('telcos');
    }

    public function update(Request $request, $telcoId)
    {
        $telco = Telco::findOrFail($telcoId);
        $telco->update($this->validated($request));

        return redirect()->route('telcos');
    }

    /**
     * Retire / bring back a package. Deactivating is refused while any SIM on
     * the package is still bound to a machine — that machine's "SimCard
     * Package" badge reads off this row, and ops would be retiring a plan
     * still in the field. Reactivating is always allowed.
     */
    public function toggleActivateDeactivate($telcoId)
    {
        $telco = Telco::findOrFail($telcoId);

        if ($telco->is_active) {
            $boundCount = $this->boundSimcardCount($telco);

            if ($boundCount > 0) {
                return redirect()->back()->withErrors([
                    'is_active' => "Cannot deactivate {$telco->name}: {$boundCount} SIM card(s) on this package are still bound to a machine.",
                ]);
            }
        }

        $telco->is_active = ! $telco->is_active;
        $telco->save();

        // Keep the caller's filters (status=inactive etc.).
        return redirect()->back();
    }

    public function delete($telcoId)
    {
        $telco = Telco::findOrFail($telcoId);
        $telco->delete();

        return redirect()->route('telcos');
    }

    /**
     * usage_provider must be a key registered in config/simcard_usage.php —
     * simcards:sync-usage resolves the class from it, so a typo would fatal
     * the cron. usage_endpoint is the optional per-package API query link;
     * blank means "use the provider's default".
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required',
            'desc' => 'nullable|string',
            'remarks' => 'nullable|string',
            'usage_provider' => ['nullable', Rule::in(array_keys((array) config('simcard_usage.providers', [])))],
            'usage_endpoint' => 'nullable|url|max:255',
            // Badge tint on the Operation Dashboard; blank = default blue.
            'color' => ['nullable', Rule::in(Telco::COLORS)],
        ]);

        // No provider ⇒ the link has nothing to override; never store it alone.
        if (empty($data['usage_provider'])) {
            $data['usage_provider'] = null;
            $data['usage_endpoint'] = null;
        } elseif (empty($data['usage_endpoint'])) {
            $data['usage_endpoint'] = null;
        }

        if (empty($data['color'])) {
            $data['color'] = null;
        }

        return $data;
    }

    /** SIM cards on this package that a machine is currently bound to. */
    protected function boundSimcardCount(Telco $telco): int
    {
        return Simcard::where('telco_id', $telco->id)
            ->whereHas('vends')
            ->count();
    }

    /** @return list<array{id:string, name:string, endpoint:string}> for the form's provider select. */
    protected function usageProviderOptions(): array
    {
        return collect((array) config('simcard_usage.providers', []))
            ->map(fn ($config, $key) => [
                'id' => $key,
                'name' => $config['label'] ?? $key,
                'endpoint' => (string) ($config['endpoint'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
