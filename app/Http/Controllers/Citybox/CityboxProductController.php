<?php

namespace App\Http\Controllers\Citybox;

use App\Http\Controllers\Controller;
use App\Http\Requests\Citybox\MapCityboxProductRequest;
use App\Models\CityboxProduct;
use App\Models\CityboxProductSyncLog;
use App\Models\Product;
use App\Services\Citybox\CatalogSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "CityBox Products" — the human mapping screen (design §5.5 / §8b).
 * Tabs: Unmapped / Mapped / Delisted / Sync log. Thin: query, render, delegate.
 */
class CityboxProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read products');
        $this->middleware('permission:update products')->only(['map', 'syncNow']);
    }

    public function index(Request $request): Response
    {
        $tab = in_array($request->tab, ['unmapped', 'mapped', 'delisted', 'log']) ? $request->tab : 'unmapped';

        $rows = match ($tab) {
            'mapped' => CityboxProduct::mapped()->with('product:id,code,name')->orderBy('name')->get(),
            'delisted' => CityboxProduct::delisted()->with('product:id,code,name')->orderBy('name')->get(),
            'log' => collect(),
            default => CityboxProduct::unmapped()->orderBy('first_seen_at', 'desc')->get(),
        };

        return Inertia::render('Citybox/Products', [
            'tab' => $tab,
            'rows' => $rows->map(fn (CityboxProduct $p) => [
                'id' => $p->id,
                'citybox_product_id' => $p->citybox_product_id,
                'name' => $p->name,
                'img_url' => $p->img_url,
                'volume' => $p->volume,
                'unit' => $p->unit,
                'class_name' => $p->class_name,
                'last_price_cents' => $p->last_price_cents,
                'first_seen_at' => $p->first_seen_at?->format('Y-m-d H:i'),
                'last_seen_at' => $p->last_seen_at?->format('Y-m-d H:i'),
                'is_delisted' => $p->is_delisted,
                'product' => $p->product ? ['id' => $p->product->id, 'code' => $p->product->code, 'name' => $p->product->name] : null,
                'suggestion' => $p->product ? null : $this->suggest($p),
            ]),
            'counts' => [
                'unmapped' => CityboxProduct::unmapped()->count(),
                'mapped' => CityboxProduct::mapped()->count(),
                'delisted' => CityboxProduct::delisted()->count(),
            ],
            'logs' => $tab === 'log'
                ? CityboxProductSyncLog::with('triggeredBy:id,name')->latest('started_at')->limit(50)->get()
                    ->map(fn ($l) => [
                        'id' => $l->id, 'source' => $l->source, 'started_at' => $l->started_at?->format('Y-m-d H:i:s'),
                        'fetched' => $l->fetched, 'added' => $l->added, 'updated' => $l->updated,
                        'delisted' => $l->delisted, 'unchanged' => $l->unchanged, 'error' => $l->error,
                        'by' => $l->triggeredBy?->name, 'details' => $l->details_json,
                    ])
                : [],
            'lastSync' => CityboxProductSyncLog::whereNull('error')->latest('finished_at')->first()?->finished_at?->format('Y-m-d H:i'),
            'enabled' => (bool) config('citybox.openapi.enabled'),
        ]);
    }

    /** Human links a CityBox SKU to a mark1 product (or unlinks with null). */
    public function map(MapCityboxProductRequest $request, int $id): RedirectResponse
    {
        $row = CityboxProduct::findOrFail($id);
        $row->fill([
            'product_id' => $request->product_id,
            'mapped_at' => $request->product_id ? now() : null,
            'mapped_by' => $request->product_id ? $request->user()->id : null,
        ])->save();

        $msg = $request->product_id
            ? 'Mapped "'.$row->name.'" to '.optional(Product::find($request->product_id))->name
            : 'Unmapped "'.$row->name.'"';

        return redirect()->back()->with('success', $msg);
    }

    /** "Sync now" — runs the catalog mirror inline (~1 API call) and flashes the counts. */
    public function syncNow(Request $request, CatalogSyncService $sync): RedirectResponse
    {
        try {
            $log = $sync->syncCatalog(CityboxProductSyncLog::SOURCE_CATALOG_MANUAL, $request->user()->id);
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['citybox' => 'CityBox sync failed: '.$e->getMessage()]);
        }

        $unmapped = CityboxProduct::unmapped()->count();

        return redirect()->back()->with('success', sprintf(
            'CityBox synced — %s. %d need mapping.', $log->summaryLine(), $unmapped,
        ));
    }

    /** Lightweight product search for the mapping picker (name/code contains). */
    public function productSearch(Request $request)
    {
        $q = trim((string) $request->q);

        return Product::query()
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('name')->limit(20)->get(['id', 'code', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name]);
    }

    /**
     * Fuzzy suggestion: their names are bilingual ("Suntory, Osmanthus… 三得利,…");
     * match the English half against mark1 product names. Best-effort, human confirms.
     */
    private function suggest(CityboxProduct $p): ?array
    {
        $english = trim(preg_split('/[\x{4e00}-\x{9fff}]/u', $p->name)[0] ?? $p->name, ' ,-');
        $first = trim(explode(',', $english)[0] ?? $english);
        if (mb_strlen($first) < 3) {
            return null;
        }
        $hit = Product::where('name', 'like', "%{$first}%")->orderBy('name')->first(['id', 'code', 'name']);

        return $hit ? ['id' => $hit->id, 'code' => $hit->code, 'name' => $hit->name] : null;
    }
}
