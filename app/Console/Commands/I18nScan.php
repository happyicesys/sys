<?php

namespace App\Console\Commands;

use App\Services\I18n\TextScanner;
use App\Services\I18n\TranslationCatalog;
use Illuminate\Console\Command;

/**
 * The translation deep scan (Brian, 2026-10-26). Default: are all marked texts in every language's
 * lang/{locale}.json (zh_CN, id)? --locale= checks one.
 *   --sync           add the missing ones (empty = "not translated yet"; the Translations page lists them)
 *   --hardcoded      every text a person reads that is not marked yet, per file (the to-do list)
 *   --file=          narrow --hardcoded to paths containing this, and list each finding
 *   --json=          write the --hardcoded findings to a file
 */
class I18nScan extends Command
{
    protected $signature = 'i18n:scan {--sync} {--hardcoded} {--file=} {--json=} {--locale=}';

    protected $description = 'Check the UI translation dictionary against the code, and list texts not marked for translation yet';

    public function handle(): int
    {
        $scanner = TextScanner::forApp();

        if ($this->option('hardcoded') || $this->option('file')) {
            return $this->hardcoded($scanner);
        }

        $locales = $this->option('locale') ? [(string) $this->option('locale')] : TranslationCatalog::translated();
        $used = $scanner->keys();
        foreach ($locales as $locale) {
            $this->check($locale, $used);
        }

        return self::SUCCESS;
    }

    private function check(string $locale, array $used): void
    {
        $path = lang_path($locale.'.json');
        $dictionary = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];

        $missing = array_diff_key($used, $dictionary);
        $empty = array_keys(array_filter($dictionary, fn ($v) => $v === '' || $v === null));
        $unused = array_diff_key($dictionary, $used);

        $this->info(sprintf('Marked texts in the code: %d · in %s.json: %d · missing: %d · not translated: %d · not used: %d',
            count($used), $locale, count($dictionary), count($missing), count($empty), count($unused)));
        foreach (array_slice(array_keys($missing), 0, 40) as $key) {
            $this->line('  missing: '.$key.'  ('.implode(', ', array_slice($used[$key], 0, 2)).')');
        }
        if (count($missing) > 40) {
            $this->line('  … and '.(count($missing) - 40).' more');
        }

        if ($this->option('sync') && $missing) {
            foreach (array_keys($missing) as $key) {
                $dictionary[$key] = '';
            }
            ksort($dictionary, SORT_STRING | SORT_FLAG_CASE);
            file_put_contents($path, json_encode($dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $this->info('Added '.count($missing).' text(s) to '.$locale.'.json — translate them there or on the Translations page.');
        }
    }

    private function hardcoded(TextScanner $scanner): int
    {
        $only = $this->option('file') ?: null;
        $findings = $scanner->hardcoded($only);
        $total = array_sum(array_map('count', $findings));

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $byKind = [];
        foreach ($findings as $items) {
            foreach ($items as $item) {
                $byKind[$item['kind']] = ($byKind[$item['kind']] ?? 0) + 1;
            }
        }
        arsort($byKind);
        $this->info(sprintf('Texts not marked for translation: %d in %d file(s)', $total, count($findings)));
        $this->line('  by kind: '.collect($byKind)->map(fn ($n, $k) => "$k $n")->implode(', '));

        if ($only !== null) {
            foreach ($findings as $file => $items) {
                $this->line("\n<comment>{$file}</comment> (".count($items).')');
                foreach ($items as $item) {
                    $this->line(sprintf('  %5d  %-12s %s', $item['line'], $item['kind'], $item['text']));
                }
            }
        } else {
            $this->table(['File', 'Texts'], collect($findings)->take(40)->map(fn ($items, $file) => [$file, count($items)])->values()->all());
        }

        return self::SUCCESS;
    }
}
