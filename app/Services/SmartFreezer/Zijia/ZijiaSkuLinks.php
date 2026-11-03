<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Models\ZijiaLibrarySku;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which freezer products reach the AI, and the one way a person links the rest (Brian, 2026-11-03).
 * A product reaches the AI when it is active on the freezer's planogram and has a barcode, the same
 * rule as FreezerRecognitionService::candidateCodes. Every screen, the Operation Dashboard and the
 * 08:30 email read coverage from here.
 */
class ZijiaSkuLinks
{
    public const REASON_POSSIBLE_MATCH = 'possible_match';

    public const REASON_NOT_REGISTERED = 'not_registered';

    public const REASON_BARCODE_UNKNOWN = 'barcode_unknown';

    private const MAX_SUGGESTIONS = 3;

    public function __construct(
        private readonly ZijiaBarcodeSync $sync,
        private readonly ZijiaAlgorithmClient $client,
    ) {}

    /**
     * Per freezer: how many of its active products the AI is asked about, and the ones it is not.
     * A barcode their library no longer lists counts as missing: it gets the whole session refused.
     *
     * @param  list<int>|null  $vendIds  null = every smart freezer
     * @return array<int, array{vend_id: int, code_label: string, total: int, on_list: int, missing: list<array<string, mixed>>}>
     */
    public function coverage(?array $vendIds = null): array
    {
        $rows = DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->join('products', 'products.id', '=', 'vend_channels.product_id')
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            ->where('vend_channels.is_active', true)
            ->when($vendIds !== null, fn ($q) => $q->whereIn('vends.id', $vendIds))
            ->select(['vends.id AS vend_id', 'vends.code', 'vends.code_prefix', 'products.id AS product_id', 'products.code AS product_code', 'products.name', 'products.barcode'])
            ->distinct()
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $unknown = array_flip($this->sync->barcodesNotInLibrary());
        $library = null;
        $missingByProduct = [];
        $out = [];
        foreach ($rows->groupBy('vend_id') as $vendId => $products) {
            $missing = [];
            foreach ($products as $p) {
                $hasBarcode = filled($p->barcode);
                if ($hasBarcode && ! isset($unknown[(int) $p->product_id])) {
                    continue;
                }
                if (! isset($missingByProduct[$p->product_id])) {
                    $library ??= $this->unlinkedEntries();
                    $suggestions = $hasBarcode ? [] : $this->suggestionsFor((string) $p->name, $library);
                    $missingByProduct[$p->product_id] = [
                        'product_id' => (int) $p->product_id,
                        'code' => $p->product_code,
                        'name' => $p->name,
                        'barcode' => $hasBarcode ? trim((string) $p->barcode) : null,
                        'reason' => match (true) {
                            $hasBarcode => self::REASON_BARCODE_UNKNOWN,
                            $suggestions !== [] => self::REASON_POSSIBLE_MATCH,
                            default => self::REASON_NOT_REGISTERED,
                        },
                        'suggestions' => $suggestions,
                    ];
                }
                $missing[] = $missingByProduct[$p->product_id];
            }
            $first = $products->first();
            $out[(int) $vendId] = [
                'vend_id' => (int) $vendId,
                'code_label' => \App\Support\VendCode::label($first->code_prefix, $first->code),
                'total' => $products->count(),
                'on_list' => $products->count() - count($missing),
                'missing' => $missing,
            ];
        }

        return $out;
    }

    /**
     * Library entries that look ours and no product carries, each with the products it may be. What
     * a person reviews, so a registered SKU cannot stay unlinked unnoticed.
     *
     * @return list<array<string, mixed>>
     */
    public function unlinkedLibrary(bool $dismissed = false): array
    {
        $entries = $this->unlinkedEntries($dismissed);
        if ($entries->isEmpty()) {
            return [];
        }
        $products = Product::withoutGlobalScopes()->whereNotNull('name')->where('name', '!=', '')
            ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))
            ->get(['id', 'code', 'name']);

        return $entries->map(function (ZijiaLibrarySku $entry) use ($products) {
            $matches = $products
                ->map(fn (Product $p) => ['product_id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'score' => ZijiaSkuMatcher::score((string) $p->name, (string) $entry->sku_name)])
                ->filter(fn (array $m) => $m['score'] >= ZijiaSkuMatcher::MIN_SCORE)
                ->sortByDesc('score')->take(self::MAX_SUGGESTIONS)->values()->all();

            return self::present($entry) + ['matches' => $matches];
        })->sortBy(fn (array $e) => [$e['matches'] === [], $e['name']])->values()->all();
    }

    /**
     * A person's link. Checked against their live library first: a code they do not list would make
     * them refuse every session of every freezer carrying the product. An existing barcode is never
     * replaced here, and a code stays on one product, as the verdict maps a code back to one product.
     *
     * @throws DomainException with the sentence to show
     */
    public function link(Product $product, ZijiaLibrarySku $entry): void
    {
        if (filled($product->barcode)) {
            throw new DomainException(__(':product already has barcode :barcode.', ['product' => $product->code, 'barcode' => $product->barcode]));
        }
        $code = (string) $entry->product_code;
        $holder = Product::withoutGlobalScopes()->where('barcode', $code)->first(['id', 'code']);
        if ($holder !== null) {
            throw new DomainException(__('Code :barcode is already on product :product.', ['barcode' => $code, 'product' => $holder->code]));
        }
        $page = $this->client->querySkus(1, 20, null, $code);
        if ($page === null) {
            throw new DomainException(__('Zijia\'s library did not answer. Nothing was changed; try again.'));
        }
        if (! collect($page['list'])->contains(fn ($s) => (string) ($s['productCode'] ?? '') === $code)) {
            throw new DomainException(__('Zijia\'s library no longer lists :barcode. Nothing was changed.', ['barcode' => $code]));
        }

        // Saved through the model so UserLogger records who linked it.
        $product->barcode = $code;
        $product->save();
    }

    public function dismiss(ZijiaLibrarySku $entry, User $by, ?CarbonInterface $at = null): void
    {
        $entry->update(['dismissed_at' => $at ?? now(), 'dismissed_by' => $by->id]);
    }

    public function restore(ZijiaLibrarySku $entry): void
    {
        $entry->update(['dismissed_at' => null, 'dismissed_by' => null]);
    }

    /** @return list<int> the freezers an operator-restricted viewer may see, null when unrestricted */
    public static function viewerVendIds(): ?array
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();
        if ($operatorId === null) {
            return null;
        }

        return Vend::withoutGlobalScopes()->where('operator_id', $operatorId)
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return Collection<int, ZijiaLibrarySku> */
    private function unlinkedEntries(bool $dismissed = false): Collection
    {
        return ZijiaLibrarySku::query()->unlinked()
            ->when($dismissed, fn ($q) => $q->whereNotNull('dismissed_at'), fn ($q) => $q->whereNull('dismissed_at'))
            ->orderBy('sku_name')->get()
            ->filter(fn (ZijiaLibrarySku $e) => $e->looksOurs())->values();
    }

    /**
     * @param  Collection<int, ZijiaLibrarySku>  $library
     * @return list<array<string, mixed>>
     */
    private function suggestionsFor(string $name, Collection $library): array
    {
        return $library
            ->map(fn (ZijiaLibrarySku $e) => self::present($e) + ['score' => ZijiaSkuMatcher::score($name, (string) $e->sku_name)])
            ->filter(fn (array $e) => $e['score'] >= ZijiaSkuMatcher::MIN_SCORE)
            ->sortByDesc('score')->take(self::MAX_SUGGESTIONS)->values()->all();
    }

    /** @return array<string, mixed> */
    private static function present(ZijiaLibrarySku $entry): array
    {
        return [
            'id' => $entry->id,
            'name' => $entry->sku_name,
            'product_code' => $entry->product_code,
            'spec' => $entry->spec,
            'updated_at' => $entry->library_updated_at?->format('Y-m-d H:i'),
        ];
    }
}
