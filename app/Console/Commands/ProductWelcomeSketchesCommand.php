<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchGenerationException;
use Illuminate\Console\Command;

/**
 * Freezer welcome sketches (ProductWelcomeSketchService). Hourly with --missing-freezer: queue a
 * drawing for every smart-freezer product with a photo and none yet. --product redraws named
 * products now (like Regenerate on Product → Edit).
 */
class ProductWelcomeSketchesCommand extends Command
{
    protected $signature = 'products:welcome-sketches
        {--missing-freezer : queue every smart-freezer product that has a photo and no sketch}
        {--product=* : product id(s) to redraw from their current photo}
        {--dry-run : list what would be queued, send nothing}';

    protected $description = 'Queue freezer welcome-sketch drawings (image model) for products';

    public function handle(ProductWelcomeSketchService $sketches): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($this->option('missing-freezer')) {
            $ids = $sketches->queueMissingForFreezers($dry);
            $this->line(($dry ? 'Would queue' : 'Queued').': '.(count($ids) ? implode(', ', $ids) : 'none'));
        }

        foreach ((array) $this->option('product') as $id) {
            $product = Product::withoutGlobalScopes()->find((int) $id);
            if ($product === null) {
                $this->warn("Product {$id} not found.");

                continue;
            }
            if ($dry) {
                $this->line("Would redraw {$product->id} {$product->name}");

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
