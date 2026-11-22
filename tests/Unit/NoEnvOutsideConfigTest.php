<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * env() returns null once config is cached, so app code reads settings through config().
 * SG 2026-11-22: a config:cache blanked the logo, the CMS link, the map provider and the
 * link in every alert email until the cache was cleared. Seeders and migrations run from
 * the command line with their own variables and are left out.
 */
class NoEnvOutsideConfigTest extends TestCase
{
    public function test_app_and_routes_read_settings_through_config(): void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        foreach (['app', 'routes'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                foreach (file($file->getPathname()) as $i => $line) {
                    $code = preg_replace('#//.*$#', '', $line);
                    if (preg_match('/(?<![\w>:$])env\(/', $code) && ! preg_match('/^\s*\*/', $code)) {
                        $found[] = substr($file->getPathname(), strlen($root) + 1).':'.($i + 1);
                    }
                }
            }
        }

        $this->assertSame([], $found, 'Read these through config(): '.implode(', ', $found));
    }
}
