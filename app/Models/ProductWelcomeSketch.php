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
 * photo changes; seed and upload are kept until someone presses Regenerate.
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
        'product_id', 'status', 'source', 'path', 'url', 'source_photo_url', 'source_photo_hash',
        'model', 'reason', 'last_error', 'requested_by', 'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
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

    /** Hand-approved art: never replaced automatically, only by Regenerate or an upload. */
    public function isCurated(): bool
    {
        return in_array($this->source, [self::SOURCE_SEED, self::SOURCE_UPLOAD], true);
    }
}
