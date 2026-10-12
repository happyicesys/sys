<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchGenerationException;
use Illuminate\Console\Command;

/**
 * Freezer welcome sketches (ProductWelcomeSketchService). Hourly with --missing-freezer: queue a
 * drawing for every smart-freezer product with a photo and none yet. --product draws a new version
 * of named products into their album (like Draw on Product → Edit). --recover puts earlier drawings
 * still in storage back into each product's album (every redraw before 2026-10-12 replaced the
 * live one and left its file behind).
 */
class ProductWelcomeSketchesCommand extends Command
{
    protected $signature = 'products:welcome-sketches
        {--missing-freezer : queue every smart-freezer product that has a photo and no sketch, then a few welcome-scene vending products}
        {--product=* : product id(s) to draw a new album version for, from their current photo}
        {--recover : add earlier drawings found in storage to their product album}
        {--dry-run : list what would be queued, send nothing}';

    protected $description = 'Queue freezer welcome-sketch drawings (image model) for products';

    public function handle(ProductWelcomeSketchService $sketches): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($this->option('missing-freezer')) {
            $ids = $sketches->queueMissingForFreezers($dry);
            $this->line(($dry ? 'Would queue' : 'Queued').': '.(count($ids) ? implode(', ', $ids) : 'none'));
        }

        if ($this->option('recover')) {
            $total = 0;
            $ids = \App\Models\ProductWelcomeSketch::query()->orderBy('product_id')->pluck('product_id');
            foreach (Product::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('id')->get() as $product) {
                $n = $sketches->recoverStored($product, $dry);
                if ($n > 0) {
                    $this->line(sprintf('%-5d %-40s %d earlier drawing(s)', $product->id, mb_substr((string) $product->name, 0, 40), $n));
                }
                $total += $n;
            }
            $this->line(($dry ? 'Would recover' : 'Recovered').": {$total}");
        }

        foreach ((array) $this->option('product') as $id) {
            $product = Product::withoutGlobalScopes()->find((int) $id);
            if ($product === null) {
                $this->warn("Product {$id} not found.");

                continue;
            }
            if ($dry) {
                $this->line("Would draw {$product->id} {$product->name}");

                continue;
            }
            try {
                $sketches->regenerate($product, null);
                $this->line("Queued {$product->id} {$product->name}");
            } catch (SketchGenerationException $e) {
                $this->warn("{$product->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
