<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchGenerationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product → Edit → Freezer welcome sketch and its album (ProductWelcomeSketchService): draw new
 * versions from the photo, shortlist, pick the one the freezers drop, delete, or upload one's own
 * drawing. JSON only — the page keeps its unsaved product form while the album changes.
 * Permission: `read products` to look, `update products` for the rest.
 */
class ProductWelcomeSketchController extends Controller
{
    public function __construct(private readonly ProductWelcomeSketchService $sketches) {}

    public function show(int $productId): JsonResponse
    {
        return response()->json($this->sketches->pageData($this->product($productId)));
    }

    public function regenerate(Request $request, int $productId): JsonResponse
    {
        $data = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:'.ProductWelcomeSketchService::MAX_PER_DRAW],
            'instructions' => ['nullable', 'string', 'max:'.ProductWelcomeSketchService::MAX_INSTRUCTIONS],
        ]);
        $product = $this->product($productId);

        return $this->run($product, fn () => $this->sketches->regenerate(
            $product, $request->user()?->id, (int) ($data['count'] ?? 1), $data['instructions'] ?? null,
        ));
    }

    public function upload(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'welcome_sketch' => ['required', 'file', 'mimes:png,webp', 'max:5000'],
        ], [
            'welcome_sketch.mimes' => 'Upload a PNG or WebP with a transparent background.',
        ]);
        $product = $this->product($productId);

        return $this->run($product, fn () => $this->sketches->storeUpload($product, $request->file('welcome_sketch'), $request->user()?->id));
    }

    public function select(Request $request, int $productId, int $versionId): JsonResponse
    {
        $product = $this->product($productId);

        return $this->run($product, fn () => $this->sketches->select($product, $versionId, $request->user()?->id));
    }

    public function shortlist(Request $request, int $productId, int $versionId): JsonResponse
    {
        $data = $request->validate(['shortlisted' => ['required', 'boolean']]);
        $product = $this->product($productId);

        return $this->run($product, fn () => $this->sketches->shortlist($product, $versionId, (bool) $data['shortlisted']));
    }

    public function destroy(int $productId, int $versionId): JsonResponse
    {
        $product = $this->product($productId);

        return $this->run($product, fn () => $this->sketches->deleteVersion($product, $versionId));
    }

    private function product(int $productId): Product
    {
        return Product::withoutGlobalScopes()->findOrFail($productId);
    }

    /** Do it, then answer with the section's fresh state; a refusal is a 422 with its reason. */
    private function run(Product $product, callable $action): JsonResponse
    {
        try {
            $action();
        } catch (SketchGenerationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['welcome_sketch' => [$e->getMessage()]]], 422);
        }

        return response()->json($this->sketches->pageData($product));
    }
}
