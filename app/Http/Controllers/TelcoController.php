<?php

namespace App\Http\Controllers;

use App\Http\Resources\TelcoResource;
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

        return Inertia::render('Telco/Index', [
            'telcos' => TelcoResource::collection(
                Telco::query()
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
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
        ]);

        // No provider ⇒ the link has nothing to override; never store it alone.
        if (empty($data['usage_provider'])) {
            $data['usage_provider'] = null;
            $data['usage_endpoint'] = null;
        } elseif (empty($data['usage_endpoint'])) {
            $data['usage_endpoint'] = null;
        }

        return $data;
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
