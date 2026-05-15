<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Campaign;

class Tag extends Model
{
    use HasFactory;

    /**
     * Classnames whose tags use the "display name + slug" split:
     *   - `name`  → preserved exactly as the user typed (display)
     *   - `slug`  → auto-derived snake_case form used for uniqueness checks
     * Other classnames keep the legacy behaviour where `name` itself is
     * mutated to snake_case (see the `name` accessor below) because Vend /
     * Product flows still match labels against `tags.name` directly.
     */
    public const DISPLAY_NAME_CLASSNAMES = [
        \App\Models\Customer::class,
    ];

    protected $fillable = [
        'classname',
        'name',
        'slug',
        'desc',
    ];

    /**
     * Name mutator.
     *
     * Only trims outer whitespace at write-time. The classname-aware
     * snake_case transform is deliberately deferred to the `saving` event
     * (see booted()) so that we don't rely on `classname` already being set
     * on the model when `$tag->name = $value` runs — fill() iterates the
     * input array in key order and Vue/Inertia sends `classname` last.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : trim((string) $value),
        );
    }

    /**
     * Build a stable snake_case slug from any display-style string.
     * Centralised here so TagController validation and the model
     * `saving` hook below agree on the exact same algorithm.
     */
    public static function makeSlug(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return strtolower(trim(preg_replace('/\s+/', '_', $value)));
    }

    /**
     * Saving hook — runs after all attributes (including `classname`) have
     * been set, so the branching logic below always sees the correct scope.
     *
     *   - Customer-scoped tags: keep `name` as-typed (display string) and
     *     auto-fill `slug` from it when the caller didn't supply one.
     *   - All other classnames: preserve the long-standing behaviour where
     *     `name` itself is snake_cased — Vend/Product/Campaign code still
     *     looks up labels via `tags.name` and would break otherwise.
     *
     * Explicit slug values from the controller are NOT overwritten so that
     * deduplication-on-validate stays authoritative.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tag) {
            if (in_array($tag->classname, self::DISPLAY_NAME_CLASSNAMES, true)) {
                if (empty($tag->slug) && ! empty($tag->name)) {
                    $tag->slug = self::makeSlug($tag->name);
                }
                return;
            }
            // Legacy snake_case-on-name behaviour for non-Customer tags.
            if (! empty($tag->name)) {
                $tag->name = self::makeSlug($tag->name);
            }
        });
    }

    public function tagBindings()
    {
        return $this->hasMany(TagBinding::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_tag')->withPivot('type')->withTimestamps();
    }
}
