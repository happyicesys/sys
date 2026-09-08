<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guard: the 0/6 success and fault predicates live in App\Support\DispenseVerdict
 * and nowhere else. Before 2026-09-08 they were spelled out inline 105 times in
 * 20 files, which is why adding code 99 was a fleet-wide risk. Any new inline
 * copy fails this test; use DispenseVerdict::sqlSale() and friends instead.
 */
class NoInlineDispensePredicateTest extends TestCase
{
    private const PATTERNS = [
        '/code = 0 OR/',
        '/(?<![,0-9])IN \(0, ?6\)/',
        '/IN \(0, 4, 5, 6\)/',
        '/\[0, ?6\]/',
    ];

    public function test_no_inline_dispense_predicate_outside_dispense_verdict(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

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
                        $offenders[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname()).':'.($no + 1).'  '.trim($line);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Inline 0/6 predicate found — use App\\Support\\DispenseVerdict:\n".implode("\n", $offenders));
    }
}
