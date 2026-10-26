<?php

namespace App\Services\I18n;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's file loader with the Translations page's wording (any language, English too) laid over the JSON dictionary, so a
 * server message (`__('Saved')`) reads exactly what the Vue pages read. Group files
 * (lang/zh_CN/validation.php …) pass through untouched.
 */
class OverridingTranslationLoader implements Loader
{
    public function __construct(private readonly Loader $inner, private readonly TranslationCatalog $catalog) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->inner->load($locale, $group, $namespace);
        if ($group === '*' && $namespace === '*') {
            try {
                $overrides = $this->catalog->overrides($locale);
            } catch (TranslationsUnavailable) {
                $overrides = [];
            }

            return array_merge($lines, array_filter($overrides, fn ($v) => $v !== ''));
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->inner->namespaces();
    }
}
