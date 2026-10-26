<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's wording for one UI text in one language, laid over the shipped `lang/{locale}.json`
 * (Translations page). Written only by App\Services\I18n\TranslationCatalog.
 */
class UiTranslation extends Model
{
    protected $fillable = ['locale', 'key', 'key_hash', 'value', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
