<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One change that reached Grab (Grab Menu Sync page). Written only by GrabMenuSyncTracker.
 */
class GrabMenuSync extends Model
{
    const KIND_COMPANY_RULE = 'company_rule';

    const KIND_STORE_RULE = 'store_rule';

    const KIND_CUSTOM_PRICE = 'custom_price';

    const KIND_SUB_CATEGORY = 'sub_category';

    const KIND_MENU_SOURCE = 'menu_source';

    const KIND_STORE_SETUP = 'store_setup';

    const KIND_PRODUCT = 'product';

    const KIND_NIGHTLY = 'nightly';

    const KIND_SELF_CHECK = 'self_check';

    const KIND_RESEND = 'resend';

    protected $fillable = ['operator_id', 'user_id', 'kind', 'summary', 'details', 'store_count'];

    protected $casts = ['details' => 'array'];

    public function stores(): HasMany
    {
        return $this->hasMany(GrabMenuSyncStore::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class)->withoutGlobalScopes();
    }
}
