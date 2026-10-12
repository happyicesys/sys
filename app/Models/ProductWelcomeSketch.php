<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The drawing a smart freezer's welcome scene drops for a product instead of its catalog photo.
 * ProductWelcomeSketchService is the only writer.
 *
 *   pending ──job──▶ generating ──▶ ready | failed
 *
 * `source`: seed (the approved hand-checked set), generated (image model, from the product photo),
 * cutout (the photo with its background removed, finished as a sticker — the free fallback) or
 * upload (a person on Product → Edit). Only generated and cutout are redrawn automatically when the
 * photo changes; seed, upload and a drawing a person picked from the album are kept.
 *
 * Since 2026-10-12 this is the LIVE row of the product's album (ProductWelcomeSketchVersion): it
 * carries a copy of the picked version (`version_id`) so the freezer menu reads one row, and
 * `selected_by` / `selected_at` say who picked it (null = it went live automatically).
 */
class ProductWelcomeSketch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_SEED = 'seed';

    public const SOURCE_GENERATED = 'generated';

    public const SOURCE_UPLOAD = 'upload';

    /** Free fallback: the photo's background removed (rembg) and finished as a sticker. */
    public const SOURCE_CUTOUT = 'cutout';

    protected $fillable = [
        'product_id', 'version_id', 'status', 'source', 'path', 'url', 'source_photo_url', 'source_photo_hash',
        'model', 'reason', 'last_error', 'requested_by', 'selected_by', 'selected_at', 'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'selected_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    /**
     * A drawing exists. A redraw in progress or a failed one keeps the previous drawing, so this
     * reads `url`, not `status`.
     */
    public function hasDrawing(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductWelcomeSketchVersion::class, 'version_id');
    }

    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    /** Hand-approved art or a person's pick from the album: never replaced automatically. */
    public function isCurated(): bool
    {
        return in_array($this->source, [self::SOURCE_SEED, self::SOURCE_UPLOAD], true) || $this->selected_by !== null;
    }
}
