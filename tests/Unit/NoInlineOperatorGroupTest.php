<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * HIPL's operator group is defined once, in App\Support\OperatorScope
 * (PARENT_GROUP_CODES for the ceiling, DEFAULT_FILTER_CODES for the filter
 * default; the Vue pages get the latter through the `defaultOperatorCodes`
 * Inertia prop). Until 2026-09-11 the default was copied into ~20 controllers
 * and exports and 27 Vue pages, so adding XO and MSW took a grep across the
 * app. This fails when a copy comes back. 'UL-ST' is the marker: every copy
 * listed it.
 */
class NoInlineOperatorGroupTest extends TestCase
{
    /** Repo-relative files still allowed to name the code, and how many times. */
    private const ALLOWED = [
        'app/Support/OperatorScope.php' => PHP_INT_MAX,
    ];

    public function test_no_file_spells_out_the_hipl_operator_group(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['app', 'resources/js'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['php', 'vue', 'js'], true)) {
                    continue;
                }

                $count = preg_match_all('/[\'"]UL-ST[\'"]/', file_get_contents($file->getPathname()));
                $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');

                if ($count > (self::ALLOWED[$relative] ?? 0)) {
                    $offenders[] = "{$relative} ({$count})";
                }
            }
        }

        $this->assertSame([], $offenders, "Use OperatorScope::DEFAULT_FILTER_CODES / defaultFilterIds() (PHP) or hiplDefaultOperators() (Vue) instead of listing operator codes:\n".implode("\n", $offenders));
    }
}
