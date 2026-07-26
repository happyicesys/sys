<?php

namespace App\Support;

/**
 * Minimal, dependency-free User-Agent parser for Admin > Visitor History.
 *
 * Deliberately NOT a composer package: this only ever has to answer "which
 * browser / OS / form factor was this admin on", which a short ordered set of
 * substring checks does reliably. Order matters — Edge and Opera both claim
 * "Chrome", Chrome claims "Safari", so the more specific brand must be tested
 * first. Anything unrecognised degrades to null rather than guessing.
 */
class UserAgentParser
{
    /**
     * @return array{device_type:?string, platform:?string, browser:?string, browser_version:?string}
     */
    public static function parse(?string $ua): array
    {
        $ua = trim((string) $ua);

        if ($ua === '') {
            return [
                'device_type'     => null,
                'platform'        => null,
                'browser'         => null,
                'browser_version' => null,
            ];
        }

        [$browser, $version] = self::browser($ua);

        return [
            'device_type'     => self::deviceType($ua),
            'platform'        => self::platform($ua),
            'browser'         => $browser,
            'browser_version' => $version,
        ];
    }

    /** Human one-liner for the table cell, e.g. "Chrome 126 · macOS". */
    public static function summary(?string $ua): string
    {
        $p = self::parse($ua);

        $browser = $p['browser'] ?: 'Unknown browser';
        if ($p['browser_version']) {
            $browser .= ' ' . $p['browser_version'];
        }

        return $p['platform'] ? $browser . ' · ' . $p['platform'] : $browser;
    }

    protected static function deviceType(string $ua): string
    {
        if (preg_match('/bot|crawler|spider|crawling|headlesschrome|python-requests|curl\//i', $ua)) {
            return 'bot';
        }

        // iPadOS 13+ reports a desktop Safari UA, so the only reliable tablet
        // tells left are the explicit iPad token and Android without "Mobile".
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|IEMobile|BlackBerry/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected static function platform(string $ua): ?string
    {
        // iOS/iPadOS before the generic "Mac OS X" check — iPhone UAs contain
        // "like Mac OS X" and would otherwise be labelled macOS.
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return 'iOS';
        }
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/Windows NT ([0-9.]+)/i', $ua, $m)) {
            $map = [
                '10.0' => 'Windows 10/11',
                '6.3'  => 'Windows 8.1',
                '6.2'  => 'Windows 8',
                '6.1'  => 'Windows 7',
            ];
            return $map[$m[1]] ?? 'Windows';
        }
        if (preg_match('/Windows/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/Mac OS X|Macintosh/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/CrOS/i', $ua)) {
            return 'ChromeOS';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }

        return null;
    }

    /**
     * @return array{0:?string, 1:?string} [name, major version]
     */
    protected static function browser(string $ua): array
    {
        // Most specific first: Edge/Opera/Samsung all masquerade as Chrome, and
        // Chrome masquerades as Safari.
        $checks = [
            ['Edge',           '/Edg(?:e|A|iOS)?\/([0-9]+)/i'],
            ['Opera',          '/(?:OPR|Opera)\/([0-9]+)/i'],
            ['Samsung Browser', '/SamsungBrowser\/([0-9]+)/i'],
            ['UC Browser',     '/UCBrowser\/([0-9]+)/i'],
            ['Firefox',        '/(?:Firefox|FxiOS)\/([0-9]+)/i'],
            ['Chrome',         '/(?:Chrome|CriOS)\/([0-9]+)/i'],
            ['Safari',         '/Version\/([0-9]+).*Safari/i'],
            // IE11 drops "MSIE" entirely and puts Trident BEFORE rv:, so both
            // orderings have to be accepted.
            ['IE',             '/MSIE ([0-9]+)/i'],
            ['IE',             '/Trident\\/.*rv:([0-9]+)/i'],
        ];

        foreach ($checks as [$name, $pattern]) {
            if (preg_match($pattern, $ua, $m)) {
                return [$name, $m[1] ?? null];
            }
        }

        if (preg_match('/Safari/i', $ua)) {
            return ['Safari', null];
        }

        return [null, null];
    }
}
