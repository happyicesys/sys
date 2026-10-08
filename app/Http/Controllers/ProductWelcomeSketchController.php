<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchGenerationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Product → Edit → Freezer welcome sketch: redraw it from the photo, or upload one's own drawing
 * (ProductWelcomeSketchService). Permission: `update products`.
 */
class ProductWelcomeSketchController extends Controller
{
    public function __construct(private readonly ProductWelcomeSketchService $sketches) {}

    public function regenerate(Request $request, int $productId): RedirectResponse
    {
        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        try {
            $this->sketches->regenerate($product, $request->user()?->id);
        } catch (SketchGenerationException $e) {
            return back()->withErrors(['welcome_sketch' => $e->getMessage()]);
        }

        return back()->with('success', 'Drawing the welcome sketch — it appears here in about a minute.');
    }

    public function upload(Request $request, int $productId): RedirectResponse
    {
        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        $request->validate([
            'welcome_sketch' => ['required', 'file', 'mimes:png,webp', 'max:5000'],
        ], [
            'welcome_sketch.mimes' => 'Upload a PNG or WebP with a transparent background.',
        ]);
        try {
            $this->sketches->storeUpload($product, $request->file('welcome_sketch'), $request->user()?->id);
        } catch (SketchGenerationException $e) {
            return back()->withErrors(['welcome_sketch' => $e->getMessage()]);
        }

        return back()->with('success', 'Welcome sketch uploaded.');
    }
}
