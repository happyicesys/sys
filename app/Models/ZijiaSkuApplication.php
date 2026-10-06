<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One modelling application of a product to Zijia's AI (Smart Freezer AI Training on Product →
 * Edit; ZijiaSkuApplicationService owns every write).
 *
 *   draft ──submit──▶ submitted ──their callback──▶ approved | rejected
 *     │                  (refused / no answer) ──▶ failed
 *
 * A rejected or failed application is never edited: a new draft copies it, so the log of what
 * was sent and answered stays intact.
 */
class ZijiaSkuApplication extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'product_id', 'application_no', 'status', 'sku_name', 'brand_name', 'spec', 'category', 'package_type',
        'product_code', 'package_image_url', 'model_pics', 'attach', 'callback_url', 'zijia_sku_id', 'sys_sku_id',
        'decision_msg', 'submitted_by', 'submitted_at', 'decided_at', 'last_error',
    ];

    protected $casts = [
        'model_pics' => 'array',
        'category' => 'integer',
        'package_type' => 'integer',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ZijiaSkuApplicationEvent::class)->orderByDesc('id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
