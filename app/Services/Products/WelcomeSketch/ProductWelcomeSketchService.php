<?php

namespace App\Services\Products\WelcomeSketch;

use App\Jobs\GenerateProductWelcomeSketch;
use App\Models\Product;
use App\Models\ProductWelcomeSketch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * The only writer of `product_welcome_sketches` (Brian, 2026-10-08): the drawing a smart freezer's
 * welcome scene drops for a product instead of its catalog photo, served to the freezer on
 * `/api/vends/{code}/menu` as `welcome_sketch`.
 *
 *  - Seed: the approved hand-checked set in resources/welcome-sketches/{product code}.webp
 *    (ProductWelcomeSketchSeeder).
 *  - Automatic: saving a product with a new photo queues a redraw by the image model
 *    (SketchGenerator, inert until OPENAI_API_KEY is set). Only a `generated` or `cutout` sketch is
 *    redrawn automatically; seed and upload art is kept until someone presses Regenerate.
 *  - Cut-out: when no image model is configured, or its drawing fails, the photo's background is
 *    removed by rembg (CutoutMaker, open source, on our server) and finished as a white-outlined
 *    sticker. A cut-out made only because no model was configured is upgraded to a real sketch once
 *    one is (the next save or the hourly sweep).
 *  - Upload: a person replaces it on Product → Edit.
 *
 * A redraw in progress or a failed one keeps the previous drawing on the freezer; the photo is the
 * fallback only when a product has never had one. Every image is stored trimmed, at most
 * `max_px` on its longest side, as transparent WebP under sys/products/{id}/welcome-sketch/.
 */
class ProductWelcomeSketchService
{
    /** Approved sketches sent as the style to copy (resources/welcome-sketches). */
    public const STYLE_REFERENCES = ['U-11', 'CC-01', 'U-89'];

    /** A pending/generating row untouched this long was abandoned; a new request may replace it. */
    public const STALE_MINUTES = 15;

    /** A freezer is nudged to re-read its menu this long after its first new sketch (debounce). */
    public const NUDGE_DELAY_SECONDS = 90;

    public function __construct(
        private readonly SketchGenerator $generator,
        private readonly CutoutMaker $cutouts,
    ) {}

    /** Something can make a drawing: the image model, or the cut-out fallback. */
    public function canDraw(): bool
    {
        return $this->generator->isConfigured() || $this->cutouts->isAvailable();
    }

    /** A cut-out made only because no model was configured, now that one is. */
    private function upgradable(ProductWelcomeSketch $sketch): bool
    {
        return $sketch->source === ProductWelcomeSketch::SOURCE_CUTOUT
            && $sketch->last_error === null
            && $this->generator->isConfigured();
    }

    public static function seedDirectory(): string
    {
        return resource_path('welcome-sketches');
    }

    public function photoUrl(Product $product): ?string
    {
        $url = $product->relationLoaded('thumbnail') ? $product->thumbnail?->full_url : $product->thumbnail()->value('full_url');

        return is_string($url) && trim($url) !== '' ? $url : null;
    }

    /**
     * Product saved on create / Product → Edit: queue a redraw when the photo is new to the sketch.
     * Never touches seed or uploaded art, never queues twice, never queues without a photo or a
     * configured image service.
     */
    public function requestAfterSave(Product $product, ?int $userId, string $reason = 'photo_saved'): ?ProductWelcomeSketch
    {
        if (! config('smart_freezer.welcome_sketch.auto_generate', true) || ! $this->canDraw()) {
            return null;
        }
        $photo = $this->photoUrl($product);
        if ($photo === null) {
            return null;
        }
        $sketch = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        if ($sketch !== null) {
            if ($sketch->isCurated()) {
                return null;
            }
            if (self::inFlight($sketch)) {
                return null;
            }
            // Drawn from this very photo already (ready, or failed on it — Regenerate retries that).
            if ($sketch->source_photo_url === $photo && ! $this->upgradable($sketch)) {
                return null;
            }
        }

        return $this->queue($product, $reason, $userId);
    }

    /** Regenerate on Product → Edit: redraw from the current photo, whatever the sketch's source. */
    public function regenerate(Product $product, ?int $userId): ProductWelcomeSketch
    {
        if (! $this->canDraw()) {
            throw new SketchGenerationException('Nothing can draw sketches on this server (no OPENAI_API_KEY and no rembg).');
        }
        if ($this->photoUrl($product) === null) {
            throw new SketchGenerationException('Add a product photo first — the sketch is drawn from it.');
        }
        $current = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        if ($current !== null && self::inFlight($current)) {
            return $current;
        }

        return $this->queue($product, 'regenerate', $userId);
    }

    /**
     * Freezers re-read `/menu` only on boot or when nudged: every active freezer carrying this
     * product gets one nudge NUDGE_DELAY_SECONDS from now, debounced per freezer, so a batch of new
     * sketches is fetched in one go.
     */
    public function nudgeFreezers(Product $product): void
    {
        $vendIds = \Illuminate\Support\Facades\DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', \App\Models\Vend::MACHINE_TYPE_SMART_FREEZER)
            ->where('vends.is_active', 1)
            ->where('vend_channels.product_id', $product->id)
            ->distinct()
            ->pluck('vends.id');
        foreach ($vendIds as $vendId) {
            if (\Illuminate\Support\Facades\Cache::add("welcome-sketch-nudge:{$vendId}", true, self::NUDGE_DELAY_SECONDS)) {
                \App\Jobs\NudgeFreezerMenu::dispatch((int) $vendId)->delay(now()->addSeconds(self::NUDGE_DELAY_SECONDS))->afterCommit();
            }
        }
    }

    /** On any smart freezer's planogram (vend_channels, kept by FreezerChannelSync). */
    public function isFreezerProduct(Product $product): bool
    {
        return self::freezerProductIds()->contains($product->id);
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    public static function freezerProductIds()
    {
        return \Illuminate\Support\Facades\DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', \App\Models\Vend::MACHINE_TYPE_SMART_FREEZER)
            ->whereNotNull('vend_channels.product_id')
            ->distinct()
            ->pluck('vend_channels.product_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Hourly sweep: every smart-freezer product with a photo and no drawing gets one queued — so a
     * product put on a freezer's mapping is drawn without anyone editing it. Products whose photo
     * already failed are not retried until the photo changes or someone presses Regenerate.
     *
     * @return list<int> product ids queued
     */
    public function queueMissingForFreezers(bool $dryRun = false): array
    {
        if (! config('smart_freezer.welcome_sketch.auto_generate', true) || ! $this->canDraw()) {
            return [];
        }
        $queued = [];
        $products = Product::withoutGlobalScopes()->with(['thumbnail', 'welcomeSketch'])
            ->whereIn('id', self::freezerProductIds())->orderBy('id')->get();
        foreach ($products as $product) {
            $sketch = $product->welcomeSketch;
            if ($this->photoUrl($product) === null || ($sketch?->hasDrawing() && ! $this->upgradable($sketch))) {
                continue;
            }
            if ($dryRun) {
                $queued[] = $product->id;

                continue;
            }
            if ($this->requestAfterSave($product, null, 'freezer_sweep') !== null) {
                $queued[] = $product->id;
            }
        }

        return $queued;
    }

    /** Queued or being drawn, and not abandoned (a worker killed mid-call leaves `generating`). */
    public static function inFlight(ProductWelcomeSketch $sketch): bool
    {
        return in_array($sketch->status, [ProductWelcomeSketch::STATUS_PENDING, ProductWelcomeSketch::STATUS_GENERATING], true)
            && $sketch->updated_at !== null && $sketch->updated_at->gt(now()->subMinutes(self::STALE_MINUTES));
    }

    private function queue(Product $product, string $reason, ?int $userId): ProductWelcomeSketch
    {
        $sketch = ProductWelcomeSketch::query()->firstOrNew(['product_id' => $product->id]);
        $sketch->fill([
            'status' => ProductWelcomeSketch::STATUS_PENDING,
            'reason' => $reason,
            'requested_by' => $userId,
            'last_error' => null,
        ])->save();
        GenerateProductWelcomeSketch::dispatch($sketch->id)->afterCommit();

        return $sketch;
    }

    /**
     * The job: claim the row (pending → generating, so a redelivered job never pays twice), redraw
     * the CURRENT photo, store it, mark it ready. A failure keeps the previous drawing.
     */
    public function generate(int $sketchId): void
    {
        $claimed = ProductWelcomeSketch::query()->whereKey($sketchId)
            ->where('status', ProductWelcomeSketch::STATUS_PENDING)
            ->update(['status' => ProductWelcomeSketch::STATUS_GENERATING, 'updated_at' => now()]);
        if ($claimed === 0) {
            return;
        }
        $sketch = ProductWelcomeSketch::query()->findOrFail($sketchId);
        $product = Product::withoutGlobalScopes()->with('thumbnail')->find($sketch->product_id);

        try {
            if ($product === null) {
                throw new SketchGenerationException('Product no longer exists.');
            }
            $photoUrl = $this->photoUrl($product) ?? throw new SketchGenerationException('The product has no photo.');
            $photo = $this->download($photoUrl);
            $origin = ['source_photo_url' => $photoUrl, 'source_photo_hash' => hash('sha256', $photo)];

            $modelError = null;
            if ($this->generator->isConfigured()) {
                try {
                    $drawing = $this->generator->generate($photo, (string) $product->name, $this->styleReferences());
                    $this->store($sketch, $product, $drawing, ProductWelcomeSketch::SOURCE_GENERATED, $origin + [
                        'model' => $this->generator->model(),
                    ]);
                    Log::info('Welcome sketch generated', ['product_id' => $product->id, 'sketch_id' => $sketch->id]);

                    return;
                } catch (SketchGenerationException $e) {
                    $modelError = $e->getMessage();
                }
            }
            if (! $this->cutouts->isAvailable()) {
                throw new SketchGenerationException($modelError ?? 'Nothing can draw sketches on this server.');
            }
            // Free fallback: the photo cut out and finished as a sticker. `last_error` keeps why the
            // model was not used (null = no model configured, so it is upgraded once one is).
            $sticker = self::sticker($this->cutouts->cutout($photo), (int) config('smart_freezer.welcome_sketch.max_px', 512));
            $this->store($sketch, $product, $sticker, ProductWelcomeSketch::SOURCE_CUTOUT, $origin + [
                'model' => $this->cutouts->name(),
            ]);
            if ($modelError !== null) {
                $sketch->update(['last_error' => 'Image model failed, used the photo cut-out: '.mb_substr($modelError, 0, 900)]);
            }
            Log::info('Welcome sketch cut out', ['product_id' => $product->id, 'sketch_id' => $sketch->id, 'model_error' => $modelError]);
        } catch (Throwable $e) {
            $sketch->update([
                'status' => ProductWelcomeSketch::STATUS_FAILED,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                // Remember which photo failed, so saving the product again does not re-pay for it.
                'source_photo_url' => $product !== null ? $this->photoUrl($product) : $sketch->source_photo_url,
            ]);
            Log::warning('Welcome sketch failed', ['sketch_id' => $sketch->id, 'error' => $e->getMessage()]);
        }
    }

    /** A person's own drawing from Product → Edit; kept until replaced or regenerated. */
    public function storeUpload(Product $product, UploadedFile $file, ?int $userId): ProductWelcomeSketch
    {
        $sketch = ProductWelcomeSketch::query()->firstOrNew(['product_id' => $product->id]);
        $sketch->requested_by = $userId;
        $sketch->reason = 'upload';

        return $this->store($sketch, $product, (string) file_get_contents($file->getRealPath()), ProductWelcomeSketch::SOURCE_UPLOAD, [
            'model' => null, 'source_photo_url' => null, 'source_photo_hash' => null,
        ]);
    }

    /** The approved set (ProductWelcomeSketchSeeder). Never overwrites a person's upload. */
    public function seed(Product $product, string $bytes): ?ProductWelcomeSketch
    {
        $sketch = ProductWelcomeSketch::query()->firstOrNew(['product_id' => $product->id]);
        if ($sketch->exists && $sketch->source === ProductWelcomeSketch::SOURCE_UPLOAD) {
            return null;
        }
        $hash = hash('sha256', $bytes);
        if ($sketch->exists && $sketch->source === ProductWelcomeSketch::SOURCE_SEED && $sketch->source_photo_hash === $hash && $sketch->url) {
            return null; // already seeded with this exact file
        }
        $sketch->reason = 'seed';

        return $this->store($sketch, $product, $bytes, ProductWelcomeSketch::SOURCE_SEED, [
            'model' => null, 'source_photo_url' => null, 'source_photo_hash' => $hash,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function store(ProductWelcomeSketch $sketch, Product $product, string $bytes, string $source, array $extra): ProductWelcomeSketch
    {
        $webp = self::normalise($bytes, (int) config('smart_freezer.welcome_sketch.max_px', 512));
        $path = "sys/products/{$product->id}/welcome-sketch/".substr(hash('sha256', $webp), 0, 16).'.webp';
        Storage::put($path, $webp, 'public');

        $sketch->fill($extra + [
            'product_id' => $product->id,
            'status' => ProductWelcomeSketch::STATUS_READY,
            'source' => $source,
            'path' => $path,
            'url' => Storage::url($path),
            'last_error' => null,
            'generated_at' => now(),
        ])->save();
        $this->nudgeFreezers($product);

        return $sketch;
    }

    /**
     * A background-removed photo finished as a sticker: soft edges hardened (no ghost halo), a
     * white outline ~3.5% of the size, a soft blue-grey shadow. Returns PNG bytes for normalise().
     */
    public static function sticker(string $cutout, int $maxPx): string
    {
        try {
            $img = new Imagick;
            $img->readImageBlob($cutout);
            $img->setIteratorIndex(0);
            $img->setImageFormat('png');
            $q = Imagick::getQuantum();
            $img->levelImage(0.35 * $q, 1.0, 0.65 * $q, Imagick::CHANNEL_ALPHA);
            $img->trimImage(0);
            $img->setImagePage(0, 0, 0, 0);
            $border = (int) max(4, round($maxPx * 0.035));
            $inner = max(16, $maxPx - 2 * $border - 8);
            $img->thumbnailImage($inner, $inner, true);
            $w = $img->getImageWidth() + 2 * $border;
            $h = $img->getImageHeight() + 2 * $border;

            $product = new Imagick;
            $product->newImage($w, $h, new \ImagickPixel('transparent'));
            $product->setImageFormat('png');
            $product->compositeImage($img, Imagick::COMPOSITE_OVER, $border, $border);

            $mask = clone $product;
            $mask->separateImageChannel(Imagick::CHANNEL_ALPHA);
            $mask->morphology(Imagick::MORPHOLOGY_DILATE, 1, \ImagickKernel::fromBuiltIn(Imagick::KERNEL_DISK, (string) $border));
            $mask->blurImage(0, 0.8);
            $outline = new Imagick;
            $outline->newImage($w, $h, new \ImagickPixel('white'));
            $outline->setImageFormat('png');
            $outline->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
            $outline->compositeImage($mask, Imagick::COMPOSITE_COPYOPACITY, 0, 0);

            $shadow = clone $outline;
            $shadow->setImageBackgroundColor(new \ImagickPixel('#0b3d5c'));
            $shadow->shadowImage(35, 5, 0, 0);

            $out = new Imagick;
            $out->newImage($w + 24, $h + 24, new \ImagickPixel('transparent'));
            $out->setImageFormat('png');
            $out->compositeImage($shadow, Imagick::COMPOSITE_OVER, 2, 7);
            $out->compositeImage($outline, Imagick::COMPOSITE_OVER, 12, 12);
            $out->compositeImage($product, Imagick::COMPOSITE_OVER, 12, 12);

            return $out->getImageBlob();
        } catch (Throwable $e) {
            throw new SketchGenerationException('The cut-out could not be finished: '.$e->getMessage(), 0, $e);
        }
    }

    /** Trim the transparent margin, fit within $maxPx, encode as transparent WebP. */
    public static function normalise(string $bytes, int $maxPx): string
    {
        try {
            $image = new Imagick;
            $image->readImageBlob($bytes);
            $image->setIteratorIndex(0);
            $image->setImageFormat('png');
            $image->trimImage(0);
            $image->setImagePage(0, 0, 0, 0);
            if ($image->getImageWidth() > $maxPx || $image->getImageHeight() > $maxPx) {
                $image->thumbnailImage($maxPx, $maxPx, true);
            }
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(85);
            $image->setOption('webp:alpha-quality', '100');
            $out = $image->getImageBlob();
            $image->clear();
        } catch (Throwable $e) {
            throw new SketchGenerationException('The image could not be read: '.$e->getMessage(), 0, $e);
        }

        return $out;
    }

    private function download(string $url): string
    {
        try {
            $response = Http::timeout(30)->get($url);
        } catch (Throwable $e) {
            throw new SketchGenerationException('The product photo could not be downloaded: '.$e->getMessage(), 0, $e);
        }
        if (! $response->successful() || $response->body() === '') {
            throw new SketchGenerationException("The product photo could not be downloaded (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    /** @return list<string> */
    private function styleReferences(): array
    {
        $refs = [];
        foreach (self::STYLE_REFERENCES as $code) {
            $file = self::seedDirectory()."/{$code}.webp";
            if (is_file($file)) {
                $refs[] = (string) file_get_contents($file);
            }
        }

        return $refs;
    }

    /** What the freezer gets on `/menu`: the drawing's URL, versioned so a redraw busts its cache. */
    public static function menuUrl(?ProductWelcomeSketch $sketch): ?string
    {
        if ($sketch === null || ! $sketch->hasDrawing()) {
            return null;
        }

        return $sketch->url.(str_contains($sketch->url, '?') ? '&' : '?').'v='.(optional($sketch->generated_at)->timestamp ?? 0);
    }

    /** @return array<string, mixed> Product → Edit "Freezer welcome sketch" section */
    public function pageData(Product $product): array
    {
        $sketch = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();

        return [
            'configured' => $this->canDraw(),
            'ai_configured' => $this->generator->isConfigured(),
            'cutout_available' => $this->cutouts->isAvailable(),
            'auto_generate' => (bool) config('smart_freezer.welcome_sketch.auto_generate', true),
            'has_photo' => $this->photoUrl($product) !== null,
            'sketch' => $sketch === null ? null : [
                'status' => $sketch->status,
                'source' => $sketch->source,
                'url' => self::menuUrl($sketch),
                'model' => $sketch->model,
                'reason' => $sketch->reason,
                'last_error' => $sketch->last_error,
                'generated_at' => optional($sketch->generated_at)->toIso8601String(),
                'updated_at' => optional($sketch->updated_at)->toIso8601String(),
                'photo_changed' => in_array($sketch->source, [ProductWelcomeSketch::SOURCE_GENERATED, ProductWelcomeSketch::SOURCE_CUTOUT], true)
                    && $sketch->source_photo_url !== null && $sketch->source_photo_url !== $this->photoUrl($product),
            ],
        ];
    }
}
