<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guard: the channel-error success / fault predicates live in
 * App\Support\DispenseVerdict and nowhere else. Before 2026-09-08 they were
 * spelled out inline 105 times in 20 files, which is why adding code 99 was a
 * fleet-wide risk. Any new inline copy fails this test; use
 * DispenseVerdict::sqlSale() and friends, SALE_CODES, or the PHP predicates.
 *
 * What it catches: the literal code lists (0/6, with or without 4/5 or 99) in
 * SQL or PHP, `code = 0 OR` / `code != 0` on an error-code column, and
 * vend_channel_error_id compared against a hard-coded FK id list (the
 * pre-2026-09-09 scopes did that with [1, 5]).
 */
class NoInlineDispensePredicateTest extends TestCase
{
    private const PATTERNS = [
        // IN (0, 6) / IN (0,6) / IN (0, 4, 5, 6) / IN (0, 6, 99) in SQL
        '/(?<![,0-9])IN \(0, ?(?:4, ?5, ?)?6(?:, ?99)?\)/i',
        // [0, 6] / [0, 6, 99] / [0, 6, 7, 9] PHP arrays
        '/\[0, ?6(?:, ?(?:99|7, ?9))?\]/',
        // code = 0 OR … / code != 0 on an error-code column (payment_methods.code is fine)
        '/(?:vend_channel_errors?|vce(?:_\w+)?|e)\.code\s*(?:=|!=|<>)\s*0\b/',
        '/vend_channel_error_code\s*(?:=|!=|<>)\s*["\']?0["\']?\b/',
        // FK id lists on vend_channel_error_id: Eloquent arrays and SQL IN (1) / NOT IN (1, 5)
        '/vend_channel_error_id\', \[\d/',
        '/vend_channel_error_id\s+(?:NOT\s+)?IN\s*\(\s*\d/i',
        // $errorCode == '0' or $errorCode == '6' (PHP, quoted or bare)
        '/\$\w*[eE]rr\w*\s*==\s*["\']?[06]["\']?\s+(?:or|\|\|)\s+\$\w*[eE]rr\w*\s*==\s*["\']?[06]["\']?/',
    ];

    public function test_no_inline_dispense_predicate_outside_dispense_verdict(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root.'/app', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || str_ends_with($file->getPathname(), 'DispenseVerdict.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $no => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                    continue; // prose may still describe the rule
                }
                $code = preg_replace('~//.*$~', '', $line); // trailing comments are prose too
                foreach (self::PATTERNS as $pattern) {
                    if (preg_match($pattern, $code)) {
                        $offenders[] = str_replace($root.'/', '', $file->getPathname()).':'.($no + 1).'  '.trim($line);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Inline channel-error predicate found — use App\\Support\\DispenseVerdict:\n".implode("\n", $offenders));
    }
}
