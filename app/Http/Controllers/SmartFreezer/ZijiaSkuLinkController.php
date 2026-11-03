<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\ZijiaLibrarySku;
use App\Services\SmartFreezer\Zijia\ZijiaSkuLinks;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AI Recognition → SKU Links: which freezer products the AI is not asked about, and a person's link
 * between a product and a SKU registered in Zijia's portal. Their library is shared by all their
 * customers, so its list is for the unrestricted operator only; a restricted viewer gets its own
 * freezers' products, and its products only through the scoped Product query.
 */
class ZijiaSkuLinkController extends Controller
{
    public function __construct(private readonly ZijiaSkuLinks $links)
    {
        $this->middleware(['permission:read ai-recognition']);
        $this->middleware(['permission:update products'])->only(['link', 'dismiss', 'restore']);
    }

    public function index(Request $request)
    {
        $seesLibrary = OperatorVendFilterScope::viewerOperatorId() === null;

        return Inertia::render('AiRecognition/SkuLinks', [
            'coverage' => array_values($this->links->coverage(ZijiaSkuLinks::viewerVendIds())),
            'library' => $seesLibrary ? $this->links->unlinkedLibrary() : [],
            'dismissed' => $seesLibrary ? $this->links->unlinkedLibrary(true) : [],
            'seesLibrary' => $seesLibrary,
            'canLink' => (bool) $request->user()?->can('update products'),
        ]);
    }

    public function link(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'library_sku_id' => ['required', 'integer'],
        ]);
        $product = Product::query()->findOrFail($data['product_id']);
        $entry = ZijiaLibrarySku::query()->findOrFail($data['library_sku_id']);

        try {
            $this->links->link($product, $entry);
        } catch (DomainException $e) {
            return back()->withErrors(['link' => $e->getMessage()]);
        }

        return back()->with('success', __(':product is on the AI\'s list as :barcode.', ['product' => $product->code, 'barcode' => $entry->product_code]));
    }

    public function dismiss(Request $request, ZijiaLibrarySku $sku)
    {
        abort_unless(OperatorVendFilterScope::viewerOperatorId() === null, 404);
        $this->links->dismiss($sku, $request->user());

        return back();
    }

    public function restore(ZijiaLibrarySku $sku)
    {
        abort_unless(OperatorVendFilterScope::viewerOperatorId() === null, 404);
        $this->links->restore($sku);

        return back();
    }
}
