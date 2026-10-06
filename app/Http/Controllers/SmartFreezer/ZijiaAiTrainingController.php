<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SmartFreezer\Zijia\ZijiaSkuApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Product → Edit → Smart Freezer AI Training: save a product's modelling application to Zijia
 * and submit it (ZijiaSkuApplicationService). Permission: `update products`.
 */
class ZijiaAiTrainingController extends Controller
{
    public function __construct(private readonly ZijiaSkuApplicationService $applications) {}

    public function save(Request $request, int $productId): RedirectResponse
    {
        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        $data = $request->validate([
            'sku_name' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:100'],
            'spec' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'integer'],
            'package_type' => ['nullable', 'integer'],
            'product_code' => ['nullable', 'string', 'max:64'],
            'package_image' => ['nullable', 'image', 'max:5000'],
            'photos' => ['nullable', 'array'],
            'photos.high' => ['nullable', 'array', 'max:20'],
            'photos.high.*' => ['image', 'max:5000'],
            'photos.horizontal' => ['nullable', 'array', 'max:20'],
            'photos.horizontal.*' => ['image', 'max:5000'],
            'photos.low' => ['nullable', 'array', 'max:20'],
            'photos.low.*' => ['image', 'max:5000'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string', 'max:2048'],
            'start_new' => ['nullable', 'boolean'],
        ]);

        $this->applications->saveDraft(
            $product,
            $data,
            array_map(fn ($files) => array_values(array_filter((array) $files)), (array) ($request->file('photos') ?? [])),
            $request->file('package_image'),
            array_values((array) ($data['remove'] ?? [])),
            $request->user(),
            (bool) ($data['start_new'] ?? false),
        );

        return back()->with('success', 'AI training draft saved.');
    }

    public function submit(Request $request, int $productId): RedirectResponse
    {
        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        $application = $this->applications->current($product);
        abort_if($application === null, 422, 'Save a draft first.');

        $application = $this->applications->submit($application, $request->user());

        return back()->with($application->status === 'submitted' ? 'success' : 'error',
            $application->status === 'submitted'
                ? "Submitted to Zijia as application {$application->application_no}. Their approval will update the barcode here."
                : "Zijia refused the application: {$application->last_error}");
    }
}
