<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use Illuminate\Database\Seeder;

/**
 * Stores the approved hand-checked welcome sketches (resources/welcome-sketches/{product code}.webp,
 * the art freezer APK v27/v28 shipped built in) on their products, so the freezer receives them
 * from mark1 like any other sketch (2026-10-08). Idempotent: an unchanged file is skipped, and a
 * person's upload is never overwritten.
 *
 *   php artisan db:seed --class=ProductWelcomeSketchSeeder
 *
 * A code shared by several products resolves to the one on a smart freezer's planogram (U-79 has
 * two products); still ambiguous → skipped and reported.
 */
class ProductWelcomeSketchSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ProductWelcomeSketchService::class);
        $freezerIds = ProductWelcomeSketchService::freezerProductIds();

        foreach (glob(ProductWelcomeSketchService::seedDirectory().'/*.webp') ?: [] as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            $candidates = Product::withoutGlobalScopes()->where('code', $code)->get(['id', 'code', 'name']);
            if ($candidates->count() > 1) {
                $onFreezer = $candidates->filter(fn ($p) => $freezerIds->contains($p->id));
                $candidates = $onFreezer->count() === 1 ? $onFreezer : $candidates;
            }
            if ($candidates->count() !== 1) {
                $this->command?->warn(sprintf('%-6s skipped: %s', $code, $candidates->isEmpty()
                    ? 'no product with this code' : 'several products ('.$candidates->pluck('id')->implode(', ').')'));

                continue;
            }
            $product = $candidates->first();
            $sketch = $service->seed($product, (string) file_get_contents($file));
            $this->command?->line(sprintf('%-6s %-5d %-40s %s', $code, $product->id, mb_substr((string) $product->name, 0, 40),
                $sketch === null ? 'unchanged / kept upload' : 'stored'));
        }
    }
}
