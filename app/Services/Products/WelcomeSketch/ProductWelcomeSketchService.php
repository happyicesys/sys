<?php

namespace App\Services\Products\WelcomeSketch;

use App\Jobs\GenerateProductWelcomeSketch;
use App\Models\Product;
use App\Models\ProductWelcomeSketch;
use App\Models\ProductWelcomeSketchVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * The only writer of `product_welcome_sketches` and its album `product_welcome_sketch_versions`
 * (Brian, 2026-10-08 / 2026-10-12): the drawing a smart freezer's welcome scene drops for a product
 * instead of its catalog photo, served to the freezer on `/api/vends/{code}/menu` as `welcome_sketch`.
 *
 * Every drawing is kept as a version in the product's album; the live row points at the one the
 * freezers drop (`version_id`). What goes live:
 *
 *  - Seed: the approved hand-checked set in resources/welcome-sketches/{product code}.webp
 *    (ProductWelcomeSketchSeeder) — live at once.
 *  - Automatic: saving a product with a new photo queues a redraw by the image model
 *    (SketchGenerator, inert until OPENAI_API_KEY is set). It goes live unless the live drawing is
 *    curated (seed, upload, or a person's pick from the album).
 *  - Draw (a person on Product → Edit, 1–MAX_PER_DRAW at a time): the drawings wait in the album and
 *    go live only when someone presses Use — or at once when the product has no drawing yet.
 *  - Cut-out: when no image model is configured, or its drawing fails, the photo's background is
 *    removed by rembg (CutoutMaker, open source, on our server) and finished as a white-outlined
 *    sticker. A cut-out made only because no model was configured is upgraded to a real sketch once
 *    one is (the next save or the hourly sweep).
 *  - Upload: a person's own drawing on Product → Edit — live at once.
 *
 * A redraw in progress or a failed one keeps the live drawing on the freezer; the photo is the
 * fallback only when a product has never had one. Every image is stored trimmed, at most
 * `max_px` on its longest side, as transparent WebP under sys/products/{id}/welcome-sketch/, named
 * by its content hash.
 */
class ProductWelcomeSketchService
{
    /** Approved sketches sent as the style to copy (resources/welcome-sketches). */
    public const STYLE_REFERENCES = ['U-11', 'CC-01', 'U-89'];

    /** A pending/generating version untouched this long was abandoned; a new request may replace it. */
    public const STALE_MINUTES = 15;

    /** A freezer is nudged to re-read its menu this long after its first new sketch (debounce). */
    public const NUDGE_DELAY_SECONDS = 90;

    /** Drawings one press of Draw may ask for (each is a paid image request). */
    public const MAX_PER_DRAW = 4;

    /** Drawings of one product queued or being drawn at once. */
    public const MAX_IN_FLIGHT = 4;

    /** Album entries sent to Product → Edit, newest first. */
    public const ALBUM_LIMIT = 80;

    /** Longest extra instructions a Draw may carry (characters). */
    public const MAX_INSTRUCTIONS = 1000;

    public function __construct(
        private readonly SketchGenerator $generator,
        private readonly CutoutMaker $cutouts,
    ) {}

    /** Something can make a drawing: the image model, or the cut-out fallback. */
    public function canDraw(): bool
    {
        return $this->generator->isConfigured() || $this->cutouts->isAvailable();
    }

    /**
     * A cut-out made only because no model was configured, now that one is — and nothing was tried
     * since (a later attempt, failed or waiting in the album, settles it).
     */
    private function upgradable(?ProductWelcomeSketch $live): bool
    {
        return $live !== null
            && $live->source === ProductWelcomeSketch::SOURCE_CUTOUT
            && $live->last_error === null
            && $this->generator->isConfigured()
            && ! ProductWelcomeSketchVersion::query()->where('product_id', $live->product_id)
                ->where('id', '>', (int) $live->version_id)->exists();
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
     * Product saved on create / Product → Edit: queue a redraw when the photo is new to the album.
     * Never touches curated art, never queues twice, never queues without a photo or a configured
     * image service, never re-pays for a photo already drawn (or already failed).
     */
    public function requestAfterSave(Product $product, ?int $userId, string $reason = 'photo_saved'): ?ProductWelcomeSketchVersion
    {
        if (! config('smart_freezer.welcome_sketch.auto_generate', true) || ! $this->canDraw()) {
            return null;
        }
        $photo = $this->photoUrl($product);
        if ($photo === null) {
            return null;
        }
        $live = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        if ($live?->isCurated() || $this->inFlightCount($product->id) > 0) {
            return null;
        }
        $triedThisPhoto = ProductWelcomeSketchVersion::query()->where('product_id', $product->id)
            ->where('source_photo_url', $photo)->exists();
        if ($triedThisPhoto && ! $this->upgradable($live)) {
            return null;
        }

        return $this->queue($product, $reason, $userId);
    }

    /**
     * Draw on Product → Edit: $count new drawings from the current photo, into the album. They go
     * live only when someone picks one — or the first to finish, when the product has none.
     * $instructions (optional) go to the image model with these drawings only, and stay on each
     * album entry so the page can show what it was asked.
     *
     * @return list<ProductWelcomeSketchVersion>
     */
    public function regenerate(Product $product, ?int $userId, int $count = 1, ?string $instructions = null): array
    {
        if (! $this->canDraw()) {
            throw new SketchGenerationException('Nothing can draw sketches on this server (no OPENAI_API_KEY and no rembg).');
        }
        if ($this->photoUrl($product) === null) {
            throw new SketchGenerationException('Add a product photo first — the sketch is drawn from it.');
        }
        $count = max(1, min(self::MAX_PER_DRAW, $count));
        $busy = $this->inFlightCount($product->id);
        if ($busy + $count > self::MAX_IN_FLIGHT) {
            throw new SketchGenerationException($busy > 0
                ? "Already drawing {$busy} — wait for them to finish (at most ".self::MAX_IN_FLIGHT.' at once).'
                : 'At most '.self::MAX_IN_FLIGHT.' drawings at once.');
        }

        $versions = [];
        for ($i = 0; $i < $count; $i++) {
            $versions[] = $this->queue($product, ProductWelcomeSketchVersion::REASON_MANUAL, $userId, $instructions);
        }

        return $versions;
    }

    /** Use this drawing: the freezers drop it from now on, and automation leaves it alone. */
    public function select(Product $product, int $versionId, ?int $userId): ProductWelcomeSketch
    {
        $version = $this->versionOf($product, $versionId);
        if (! $version->isReady()) {
            throw new SketchGenerationException('Only a finished drawing can be used.');
        }

        return $this->promote($version, $userId);
    }

    public function shortlist(Product $product, int $versionId, bool $on): ProductWelcomeSketchVersion
    {
        $version = $this->versionOf($product, $versionId);
        $version->update(['shortlisted_at' => $on ? ($version->shortlisted_at ?? now()) : null]);

        return $version;
    }

    /**
     * Remove a drawing from the album (never the live one). Its file goes too unless another entry
     * holds the same image (paths are content hashes).
     */
    public function deleteVersion(Product $product, int $versionId): void
    {
        $version = $this->versionOf($product, $versionId);
        $live = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        if ($live !== null && (int) $live->version_id === $version->id) {
            throw new SketchGenerationException('This is the drawing on the freezers — use another one first.');
        }
        $path = $version->path;
        $version->delete();
        if ($path !== null && $path !== ''
            && ! ProductWelcomeSketchVersion::query()->where('path', $path)->exists()
            && ! ProductWelcomeSketch::query()->where('path', $path)->exists()) {
            Storage::delete($path);
        }
    }

    /**
     * Earlier drawings still in storage but in no album (every redraw before 2026-10-12 replaced
     * the live one and left its file behind): each becomes an album entry, `recovered`. A file that
     * is the product's approved seed art comes back as seed.
     *
     * @return int entries added
     */
    public function recoverStored(Product $product, bool $dryRun = false): int
    {
        $known = ProductWelcomeSketchVersion::query()->where('product_id', $product->id)->pluck('path')->filter()->flip();
        $seedPath = null;
        $seedFile = self::seedDirectory().'/'.$product->code.'.webp';
        if ($product->code && is_file($seedFile)) {
            try {
                $webp = self::normalise((string) file_get_contents($seedFile), (int) config('smart_freezer.welcome_sketch.max_px', 512));
                $seedPath = "sys/products/{$product->id}/welcome-sketch/".substr(hash('sha256', $webp), 0, 16).'.webp';
            } catch (SketchGenerationException) {
                $seedPath = null;
            }
        }

        $added = 0;
        foreach (Storage::files("sys/products/{$product->id}/welcome-sketch") as $path) {
            if (! str_ends_with($path, '.webp') || $known->has($path)) {
                continue;
            }
            $added++;
            if ($dryRun) {
                continue;
            }
            $at = \Illuminate\Support\Carbon::createFromTimestamp(Storage::lastModified($path));
            $version = new ProductWelcomeSketchVersion([
                'product_id' => $product->id,
                'status' => ProductWelcomeSketch::STATUS_READY,
                'source' => $path === $seedPath ? ProductWelcomeSketch::SOURCE_SEED : ProductWelcomeSketch::SOURCE_GENERATED,
                'path' => $path,
                'url' => Storage::url($path),
                'reason' => ProductWelcomeSketchVersion::REASON_RECOVERED,
                'generated_at' => $at,
            ]);
            $version->created_at = $at;
            $version->save();
        }

        return $added;
    }

    private function versionOf(Product $product, int $versionId): ProductWelcomeSketchVersion
    {
        return ProductWelcomeSketchVersion::query()->where('product_id', $product->id)->findOrFail($versionId);
    }

    private function inFlightCount(int $productId): int
    {
        return ProductWelcomeSketchVersion::query()->where('product_id', $productId)
            ->whereIn('status', [ProductWelcomeSketch::STATUS_PENDING, ProductWelcomeSketch::STATUS_GENERATING])
            ->where('updated_at', '>', now()->subMinutes(self::STALE_MINUTES))
            ->count();
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
            ->pluck('vends.id')
            // A big-board vending app 315+ carrying it re-reads `/thumbnails` (HAPPYHOUR frame) too.
            ->merge(self::welcomeVendingVends()->filter(fn ($v) => \App\Services\HappyHour\HappyHourEligibility::supportsVending($v))
                ->pluck('id')->intersect(
                    \Illuminate\Support\Facades\DB::table('vend_channels')->where('product_id', $product->id)->distinct()->pluck('vend_id')
                ));
        foreach ($vendIds->unique() as $vendId) {
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
     * Active vending machines that SHOW the welcome scene - the only ones whose screen ever loads a
     * sketch: on a UI Setting with `enableWelcomeScene` on, or served the bear by
     * HappyHourWelcomeFallback (eligible, in an active Happy Hour campaign, no UI Setting).
     * Merely being able to (app 315+) is not enough (2026-10-14: with the whole big-board fleet
     * on 316 that would have paid to draw every vending product for screens that never show it).
     * Light columns only; eligibility is decided in PHP, in one place.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Vend>
     */
    public static function welcomeVendingVends()
    {
        $sceneOn = \Illuminate\Support\Facades\DB::table('apk_setting_vend as sv')
            ->join('apk_settings as s', 's.id', '=', 'sv.apk_setting_id')
            ->where('s.settings_parameter_json->enableWelcomeScene', 'true')
            ->pluck('sv.vend_id')->map(fn ($id) => (int) $id)->all();
        $fallback = \App\Services\HappyHour\HappyHourWelcomeFallback::campaignVendIdsWithoutUiSetting();

        return \App\Models\Vend::withoutGlobalScopes()
            ->where('machine_type', \App\Models\Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where('is_active', 1)
            ->whereIn('id', array_values(array_unique([...$sceneOn, ...$fallback])))
            ->get(['id', 'machine_type', 'is_active', 'apk_version_code', 'apk_ver_json'])
            ->filter(fn ($v) => in_array($v->id, $sceneOn, true) || \App\Services\HappyHour\HappyHourEligibility::supportsVending($v))
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, int> products on those vending machines' channels */
    public static function welcomeVendingProductIds()
    {
        $vendIds = self::welcomeVendingVends()->pluck('id');
        if ($vendIds->isEmpty()) {
            return collect();
        }

        return \Illuminate\Support\Facades\DB::table('vend_channels')
            ->whereIn('vend_id', $vendIds)
            ->where('is_active', 1) // a retired channel's product is never on the screen
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Hourly sweep: every smart-freezer product with a photo and no drawing gets one queued — so a
     * product put on a freezer's mapping is drawn without anyone editing it. Products whose photo
     * already failed are not retried until the photo changes or someone presses Draw.
     *
     * Then the products of the vending machines that show the welcome scene
     * (welcomeVendingProductIds), at most `happy_hour.vending_sketch_sweep_limit` per run: each is a
     * paid draw holding a `low` worker for up to 150 s, so a backlog (~80 on 2026-10-12) is spread
     * over the hours instead of landing on the queue at once. Freezer products are never held back.
     *
     * @return list<int> product ids queued
     */
    public function queueMissingForFreezers(bool $dryRun = false): array
    {
        if (! config('smart_freezer.welcome_sketch.auto_generate', true) || ! $this->canDraw()) {
            return [];
        }
        $queued = [];
        $freezerIds = self::freezerProductIds();
        $vendingIds = self::welcomeVendingProductIds()->diff($freezerIds);
        $vendingBudget = max(0, (int) config('happy_hour.vending_sketch_sweep_limit', 6));
        $products = Product::withoutGlobalScopes()->with(['thumbnail', 'welcomeSketch'])
            ->whereIn('id', $freezerIds->merge($vendingIds)->unique()->values())->orderBy('id')->get();
        $vendingOnly = array_flip($vendingIds->all());
        // Freezer products first (unlimited, as before), then the vending ones within the budget.
        $products = $products->sortBy(fn (Product $p) => isset($vendingOnly[$p->id]) ? 1 : 0, SORT_REGULAR)->values();
        foreach ($products as $product) {
            $sketch = $product->welcomeSketch;
            if ($this->photoUrl($product) === null || ($sketch?->hasDrawing() && ! $this->upgradable($sketch))) {
                continue;
            }
            $isVendingOnly = isset($vendingOnly[$product->id]);
            if ($isVendingOnly && $vendingBudget < 1) {
                continue;
            }
            // Only a draw actually queued spends the vending budget (a photo that already failed
            // is skipped by requestAfterSave and must not starve the others hour after hour).
            if ($dryRun || $this->requestAfterSave($product, null, $isVendingOnly ? 'vending_sweep' : 'freezer_sweep') !== null) {
                $queued[] = $product->id;
                $vendingBudget -= $isVendingOnly ? 1 : 0;
            }
        }

        return $queued;
    }

    /** Queued or being drawn, and not abandoned (a worker killed mid-call leaves `generating`). */
    public static function inFlight(ProductWelcomeSketchVersion $version): bool
    {
        return in_array($version->status, [ProductWelcomeSketch::STATUS_PENDING, ProductWelcomeSketch::STATUS_GENERATING], true)
            && $version->updated_at !== null && $version->updated_at->gt(now()->subMinutes(self::STALE_MINUTES));
    }

    private function queue(Product $product, string $reason, ?int $userId, ?string $instructions = null): ProductWelcomeSketchVersion
    {
        $instructions = trim((string) $instructions);
        $version = ProductWelcomeSketchVersion::query()->create([
            'product_id' => $product->id,
            'status' => ProductWelcomeSketch::STATUS_PENDING,
            'reason' => $reason,
            'instructions' => $instructions !== '' ? mb_substr($instructions, 0, self::MAX_INSTRUCTIONS) : null,
            'requested_by' => $userId,
            // The photo it is drawn from, so a save meanwhile does not queue the same photo again.
            'source_photo_url' => $this->photoUrl($product),
        ]);
        GenerateProductWelcomeSketch::dispatch($version->id)->afterCommit();

        return $version;
    }

    /**
     * The job: claim the version (pending → generating, so a redelivered job never pays twice),
     * redraw the CURRENT photo, store it, mark it ready, then put it live if it is due (promoteIfDue).
     * A failure is kept on the version; the live drawing is untouched.
     */
    public function generate(int $versionId): void
    {
        $claimed = ProductWelcomeSketchVersion::query()->whereKey($versionId)
            ->where('status', ProductWelcomeSketch::STATUS_PENDING)
            ->update(['status' => ProductWelcomeSketch::STATUS_GENERATING, 'updated_at' => now()]);
        if ($claimed === 0) {
            return;
        }
        $version = ProductWelcomeSketchVersion::query()->findOrFail($versionId);
        $product = Product::withoutGlobalScopes()->with('thumbnail')->find($version->product_id);

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
                    $drawing = $this->generator->generate($photo, (string) $product->name, $this->styleReferences(), $version->instructions);
                    $this->storeVersion($version, $product, $drawing, ProductWelcomeSketch::SOURCE_GENERATED, $origin + [
                        'model' => $this->generator->model(),
                    ]);
                    Log::info('Welcome sketch generated', ['product_id' => $product->id, 'version_id' => $version->id]);
                    $this->promoteIfDue($version);

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
            $this->storeVersion($version, $product, $sticker, ProductWelcomeSketch::SOURCE_CUTOUT, $origin + [
                'model' => $this->cutouts->name(),
                'last_error' => $modelError !== null ? 'Image model failed, used the photo cut-out: '.mb_substr($modelError, 0, 900) : null,
            ]);
            Log::info('Welcome sketch cut out', ['product_id' => $product->id, 'version_id' => $version->id, 'model_error' => $modelError]);
            $this->promoteIfDue($version);
        } catch (Throwable $e) {
            $version->update([
                'status' => ProductWelcomeSketch::STATUS_FAILED,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'source_photo_url' => $product !== null ? ($this->photoUrl($product) ?? $version->source_photo_url) : $version->source_photo_url,
            ]);
            Log::warning('Welcome sketch failed', ['version_id' => $version->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * A finished drawing goes live when the product has none, or — when an automatic request
     * asked for it — unless a person's pick or approved art is live. A person's Draw waits in the
     * album.
     */
    private function promoteIfDue(ProductWelcomeSketchVersion $version): void
    {
        $live = ProductWelcomeSketch::query()->where('product_id', $version->product_id)->first();
        $due = $live === null || ! $live->hasDrawing()
            || ($version->reason !== ProductWelcomeSketchVersion::REASON_MANUAL && ! $live->isCurated());
        if ($due) {
            $this->promote($version, null);
        }
    }

    /** Put a version live: copy it onto the product's live row and tell its freezers. */
    private function promote(ProductWelcomeSketchVersion $version, ?int $selectedBy): ProductWelcomeSketch
    {
        $live = ProductWelcomeSketch::query()->firstOrNew(['product_id' => $version->product_id]);
        $live->fill([
            'version_id' => $version->id,
            'status' => ProductWelcomeSketch::STATUS_READY,
            'source' => $version->source,
            'path' => $version->path,
            'url' => $version->url,
            'source_photo_url' => $version->source_photo_url,
            'source_photo_hash' => $version->source_photo_hash,
            'model' => $version->model,
            'reason' => $version->reason,
            'last_error' => $version->last_error,
            'requested_by' => $version->requested_by,
            'selected_by' => $selectedBy,
            'selected_at' => $selectedBy !== null ? now() : null,
            'generated_at' => $version->generated_at ?? now(),
        ])->save();
        if ($version->product !== null) {
            $this->nudgeFreezers($version->product);
        }

        return $live;
    }

    /** A person's own drawing from Product → Edit: into the album and live at once. */
    public function storeUpload(Product $product, UploadedFile $file, ?int $userId): ProductWelcomeSketch
    {
        $version = new ProductWelcomeSketchVersion(['product_id' => $product->id, 'reason' => 'upload', 'requested_by' => $userId]);
        $this->storeVersion($version, $product, (string) file_get_contents($file->getRealPath()), ProductWelcomeSketch::SOURCE_UPLOAD, []);

        return $this->promote($version, $userId);
    }

    /** The approved set (ProductWelcomeSketchSeeder). Never over an upload or a person's pick. */
    public function seed(Product $product, string $bytes): ?ProductWelcomeSketch
    {
        $live = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        if ($live !== null && ($live->source === ProductWelcomeSketch::SOURCE_UPLOAD || $live->selected_by !== null)) {
            return null;
        }
        $hash = hash('sha256', $bytes);
        if ($live !== null && $live->source === ProductWelcomeSketch::SOURCE_SEED && $live->source_photo_hash === $hash && $live->url) {
            return null; // already seeded with this exact file
        }
        $version = new ProductWelcomeSketchVersion(['product_id' => $product->id, 'reason' => 'seed']);
        $this->storeVersion($version, $product, $bytes, ProductWelcomeSketch::SOURCE_SEED, ['source_photo_hash' => $hash]);

        return $this->promote($version, null);
    }

    /** @param  array<string, mixed>  $extra */
    private function storeVersion(ProductWelcomeSketchVersion $version, Product $product, string $bytes, string $source, array $extra): ProductWelcomeSketchVersion
    {
        $webp = self::normalise($bytes, (int) config('smart_freezer.welcome_sketch.max_px', 512));
        $path = "sys/products/{$product->id}/welcome-sketch/".substr(hash('sha256', $webp), 0, 16).'.webp';
        Storage::put($path, $webp, 'public');

        $version->fill($extra + [
            'product_id' => $product->id,
            'status' => ProductWelcomeSketch::STATUS_READY,
            'source' => $source,
            'path' => $path,
            'url' => Storage::url($path),
            'last_error' => null,
            'generated_at' => now(),
        ])->save();
        $version->setRelation('product', $product);

        return $version;
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

    /** @return array<string, mixed> Product → Edit "Freezer welcome sketch" section and its album */
    public function pageData(Product $product): array
    {
        $photo = $this->photoUrl($product);
        $live = ProductWelcomeSketch::query()->with('selector:id,name')->where('product_id', $product->id)->first();
        $hasLive = $live !== null && $live->hasDrawing();
        $liveVersionId = $hasLive && $live->version_id !== null ? (int) $live->version_id : null;
        $versions = ProductWelcomeSketchVersion::query()->with('requester:id,name')
            ->where('product_id', $product->id)
            ->orderByRaw('COALESCE(generated_at, created_at) DESC')->orderByDesc('id')
            ->limit(self::ALBUM_LIMIT)->get();
        $iso = fn ($at) => optional($at)->toIso8601String();

        return [
            'configured' => $this->canDraw(),
            'ai_configured' => $this->generator->isConfigured(),
            'cutout_available' => $this->cutouts->isAvailable(),
            'auto_generate' => (bool) config('smart_freezer.welcome_sketch.auto_generate', true),
            'has_photo' => $photo !== null,
            'photo_url' => $photo,
            'max_per_draw' => self::MAX_PER_DRAW,
            'max_in_flight' => self::MAX_IN_FLIGHT,
            'in_flight' => $this->inFlightCount($product->id),
            'max_instructions' => self::MAX_INSTRUCTIONS,
            // What the last person's Draw asked for, to start the next one from.
            'last_instructions' => ProductWelcomeSketchVersion::query()->where('product_id', $product->id)
                ->where('reason', ProductWelcomeSketchVersion::REASON_MANUAL)
                ->latest('id')->value('instructions'),
            'live' => ! $hasLive ? null : [
                'version_id' => $liveVersionId,
                'source' => $live->source,
                'url' => self::menuUrl($live),
                'model' => $live->model,
                'last_error' => $live->last_error,
                'generated_at' => $iso($live->generated_at),
                'selected_by' => $live->selector?->name,
                'selected_at' => $iso($live->selected_at),
                'curated' => $live->isCurated(),
                'photo_changed' => in_array($live->source, [ProductWelcomeSketch::SOURCE_GENERATED, ProductWelcomeSketch::SOURCE_CUTOUT], true)
                    && $live->source_photo_url !== null && $live->source_photo_url !== $photo,
            ],
            'versions' => $versions->map(fn (ProductWelcomeSketchVersion $v) => [
                'id' => $v->id,
                // pending / generating / ready / failed, and `abandoned` for a draw a dead worker left.
                'status' => in_array($v->status, [ProductWelcomeSketch::STATUS_PENDING, ProductWelcomeSketch::STATUS_GENERATING], true)
                    && ! self::inFlight($v) ? 'abandoned' : $v->status,
                'source' => $v->source,
                'url' => $v->url,
                'model' => $v->model,
                'reason' => $v->reason,
                'instructions' => $v->instructions,
                'last_error' => $v->last_error,
                'at' => $iso($v->generated_at ?? $v->created_at),
                'requested_by' => $v->requester?->name,
                'shortlisted' => $v->shortlisted_at !== null,
                'is_live' => $v->id === $liveVersionId,
                // null = not known (an upload, seed art, or a drawing recovered from storage).
                'from_current_photo' => $v->source_photo_url === null ? null : $v->source_photo_url === $photo,
            ])->values()->all(),
        ];
    }
}
