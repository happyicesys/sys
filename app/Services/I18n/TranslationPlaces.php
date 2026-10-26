<?php

namespace App\Services\I18n;

use Illuminate\Support\Facades\Cache;

/**
 * Where each UI text is shown (Brian, 2026-10-26: "include the place where it should be shown"), for the
 * Translations page and the Edit wording box. One English text can sit on many pages and shares one wording,
 * so an admin sees every place a change reaches. Built from the code by TextScanner::keys() and cached until
 * the next deploy (the build manifest changes on every deploy).
 */
class TranslationPlaces
{
    /** @return array<string, string[]> key => place labels, sorted */
    public function all(): array
    {
        $manifest = public_path('build/manifest.json');
        $stamp = is_file($manifest) ? filemtime($manifest).'-'.filesize($manifest) : 'dev';

        return Cache::remember('i18n:places:'.$stamp, now()->addDays(30), function () {
            $scanner = TextScanner::forApp();
            $titles = [];
            $out = [];
            foreach ($scanner->keys() as $key => $files) {
                $labels = array_map(fn (string $file) => $this->label($file, $titles), $files);
                $labels = array_values(array_unique($labels));
                sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
                $out[$key] = $labels;
            }

            return $out;
        });
    }

    /** @return string[] */
    public function for(string $key): array
    {
        return $this->all()[$key] ?? [];
    }

    /** @return array<string, int> place label => number of texts there */
    public function pages(): array
    {
        $count = [];
        foreach ($this->all() as $labels) {
            foreach ($labels as $label) {
                $count[$label] = ($count[$label] ?? 0) + 1;
            }
        }
        ksort($count, SORT_NATURAL | SORT_FLAG_CASE);

        return $count;
    }

    /** A person-readable name for a file: its page title, else its folder path. */
    private function label(string $file, array &$titles): string
    {
        if (str_starts_with($file, 'resources/js/Layouts/')) {
            return 'Menu & top bar';
        }
        if (str_starts_with($file, 'resources/js/Pages/')) {
            $path = substr($file, strlen('resources/js/Pages/'), -4);
            $titles[$file] ??= $this->pageTitle($file);

            return $titles[$file] !== null ? "{$titles[$file]} ({$path})" : $path;
        }
        if (str_starts_with($file, 'resources/js/Components/')) {
            return 'Shared: '.basename($file, '.vue');
        }
        if (str_starts_with($file, 'app/')) {
            return 'Server message: '.basename($file, '.php');
        }

        return 'Shared: '.basename($file);
    }

    private function pageTitle(string $file): ?string
    {
        $source = @file_get_contents(base_path($file)) ?: '';
        if (preg_match('/<Head\s+:title="\$t\(\'((?:\\\\.|[^\'])+)\'/', $source, $m)
            || preg_match('/<Head\s+title="([^"]+)"/', $source, $m)) {
            $title = str_replace("\\'", "'", $m[1]);

            return trim(preg_replace('/\s*:\w+\s*$/', '', $title)) ?: null;
        }

        return null;
    }
}
