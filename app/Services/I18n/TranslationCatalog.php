<?php

namespace App\Services\I18n;

use App\Models\UiTranslation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The UI's languages (Brian, 2026-10-26: English by default, Simplified Chinese on request).
 *
 * A text's KEY is its English wording as written in the code, so an untranslated text simply shows in
 * English. Each language's wording is the shipped `lang/{locale}.json` (none for English) with the
 * wording people changed on the Translations page (`ui_translations`, one row per changed text) laid
 * over it — so ops can reword any text, Chinese or English, without a deploy (Brian, 2026-10-26: "they
 * might need a new wording or different content"). The file stays the source for new texts: a row
 * per label in the database would have to be inserted on every deployment's own DB. One dictionary serves the Vue pages (fetched once per version,
 * see TranslationDictionaryController) and the server (OverridingTranslationLoader, so `__()` in PHP
 * reads the same words).
 */
class TranslationCatalog
{
    public const DEFAULT_LOCALE = 'en';

    /** Code => the language's own name, as the switch shows it. */
    public const LOCALES = [
        'en' => 'English',
        'zh_CN' => '简体中文',
    ];

    /** @var array<string, array<string, string>> */
    private array $base = [];

    public static function normalize(?string $locale): string
    {
        return $locale !== null && array_key_exists($locale, self::LOCALES) ? $locale : self::DEFAULT_LOCALE;
    }

    public function path(string $locale): string
    {
        return lang_path($locale.'.json');
    }

    /** The shipped wording, empty entries dropped (an empty value means "not translated yet"). */
    public function base(string $locale): array
    {
        if (! array_key_exists($locale, $this->base)) {
            $path = $this->path($locale);
            $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $this->base[$locale] = array_filter(is_array($json) ? $json : [], fn ($v) => is_string($v) && $v !== '');
        }

        return $this->base[$locale];
    }

    /** What people changed on the Translations page. Cached until the next change. */
    public function overrides(string $locale): array
    {
        return Cache::rememberForever($this->overridesKey($locale), function () use ($locale) {
            try {
                return UiTranslation::where('locale', $locale)->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                // Before the table exists (the seconds between a deploy and its migration) the shipped
                // wording still serves. Thrown, so nothing is cached.
                Log::warning('ui_translations unreadable', ['error' => $e->getMessage()]);

                throw new TranslationsUnavailable;
            }
        });
    }

    /** The full wording for a language: shipped, then people's changes on top. */
    public function dictionary(string $locale): array
    {
        try {
            $overrides = $this->overrides($locale);
        } catch (TranslationsUnavailable) {
            $overrides = [];
        }

        return array_merge($this->base($locale), array_filter($overrides, fn ($v) => $v !== ''));
    }

    /**
     * Changes whenever the shipped file or anyone's wording changes, so a browser can keep the
     * dictionary forever under `?v=`.
     */
    public function version(string $locale): string
    {
        $path = $this->path($locale);
        $stamp = is_file($path) ? filemtime($path).'-'.filesize($path) : 'none';

        return Cache::rememberForever($this->versionKey($locale).':'.$stamp, function () use ($locale) {
            $dictionary = $this->dictionary($locale);

            // 'none' = nothing to fetch (English with no reworded text).
            return $dictionary === [] ? 'none' : substr(sha1(json_encode($dictionary)), 0, 12);
        });
    }

    /** Set (or with an empty value, remove) a person's wording for one text. */
    public function setOverride(string $locale, string $key, ?string $value, ?User $by): void
    {
        $hash = sha1($key);
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            UiTranslation::where('locale', $locale)->where('key_hash', $hash)->delete();
        } else {
            UiTranslation::updateOrCreate(
                ['locale' => $locale, 'key_hash' => $hash],
                ['key' => $key, 'value' => $value, 'updated_by' => $by?->id],
            );
        }
        $this->flush($locale);
    }

    public function flush(string $locale): void
    {
        Cache::forget($this->overridesKey($locale));
        // The version key carries the file stamp; bump a generation so every stamp's entry is dropped.
        Cache::forever($this->generationKey($locale), (int) Cache::get($this->generationKey($locale), 0) + 1);
    }

    private function overridesKey(string $locale): string
    {
        return "i18n:overrides:{$locale}";
    }

    private function versionKey(string $locale): string
    {
        return "i18n:version:{$locale}:g".(int) Cache::get($this->generationKey($locale), 0);
    }

    private function generationKey(string $locale): string
    {
        return "i18n:generation:{$locale}";
    }
}
