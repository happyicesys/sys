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
        $tab = in_array($request->tab, ['catalog', 'delisted', 'log']) ? $request->tab : 'catalog';

        $rows = match ($tab) {
            'delisted' => CityboxProduct::delisted()->with('product:id,code,name')->orderBy('name')->get(),
            'log' => collect(),
            default => CityboxProduct::where('is_delisted', false)->with('product:id,code,name')->orderBy('name')->get(),
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
            ]),
            'counts' => [
                'catalog' => CityboxProduct::where('is_delisted', false)->count(),
                'unlinked' => CityboxProduct::unmapped()->count(), // should be 0 after any sync; >0 ⇒ operator not seeded
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

    /**
     * Re-point a CityBox SKU at a different mark1 product. NOT surfaced in the UI
     * since 2026-08-19 (SKUs get their product automatically — see
     * CatalogSyncService::ensureMark1Products); kept as an escape hatch.
     */
    public function map(MapCityboxProductRequest $request, int $id): RedirectResponse
    {
        $row = CityboxProduct::findOrFail($id);
        $row->fill([
            'product_id' => $request->product_id,
            'mapped_at' => $request->product_id ? now() : null,
            'mapped_by' => $request->product_id ? $request->user()->id : null,
        ])->save();

        // A product that a chiller sells lives on the mark1 ledger (§8.1): set
        // the source at mapping time unless a human already chose one.
        $product = $request->product_id ? Product::find($request->product_id) : null;
        if ($product && $product->warehouseQtySource() === \App\Enums\WarehouseQtySource::Cms) {
            $product->forceFill(['warehouse_qty_source' => \App\Enums\WarehouseQtySource::Ledger->value])->save();
        }

        $msg = $product
            ? 'Mapped "'.$row->name.'" to '.$product->name.' (warehouse qty from mark1 ledger)'
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

        $created = count($log->details_json['products_created'] ?? []);

        return redirect()->back()->with('success', sprintf(
            'CityBox synced — %s; %d mark1 product(s) created.', $log->summaryLine(), $created,
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
}
