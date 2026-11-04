<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One drawing in a product's welcome-sketch album (Brian, 2026-10-12). Every draw, cut-out, upload
 * and seed lands here; the freezers drop only the one ProductWelcomeSketch points at (`version_id`).
 * ProductWelcomeSketchService is the only writer.
 *
 *   pending ──job──▶ generating ──▶ ready | failed
 *
 * `reason` says who asked: `regenerate` (a person pressed Draw — the drawing waits in the album) or
 * an automatic one (created, photo_saved, freezer_product, freezer_sweep — it goes live unless a
 * person's pick or approved art is live). `recovered` = an earlier drawing found in storage.
 *
 * A drawn entry is then checked against its photo, which never changes what is live:
 *
 *   NULL (never) ──▶ pending ──job──▶ checking ──▶ passed | flagged | failed
 */
class ProductWelcomeSketchVersion extends Model
{
    /** A person pressed Draw on Product → Edit: the result waits in the album. */
    public const REASON_MANUAL = 'regenerate';

    /** An earlier drawing found in storage (`products:welcome-sketches --recover`). */
    public const REASON_RECOVERED = 'recovered';

    public const CHECK_PENDING = 'pending';

    public const CHECK_CHECKING = 'checking';

    public const CHECK_PASSED = 'passed';

    public const CHECK_FLAGGED = 'flagged';

    public const CHECK_FAILED = 'failed';

    protected $fillable = [
        'product_id', 'status', 'source', 'path', 'url', 'source_photo_url', 'source_photo_hash',
        'model', 'reason', 'instructions', 'last_error', 'requested_by', 'generated_at', 'shortlisted_at',
        'check_status', 'check_issues', 'check_model', 'check_error', 'checked_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'shortlisted_at' => 'datetime',
        'check_issues' => 'array',
        'checked_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === ProductWelcomeSketch::STATUS_READY && $this->url !== null && $this->url !== '';
    }
}
