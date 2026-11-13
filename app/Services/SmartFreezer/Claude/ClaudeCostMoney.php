<?php

namespace App\Services\SmartFreezer\Claude;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Claude's costs in this site's own money (Brian, 2026-11-13: "when I sign in the Singapore one it shows SGD, the
 * Indonesia one rupiah"). Anthropic bills in US dollars and every row keeps that, as integer `cost_usd_micros`; this
 * class is the only place it is converted, for display only.
 *
 * The site's currency: CLAUDE_FREEZER_AI_CURRENCY when set, else the one currency every operator on this server
 * shares (IDN → IDR, LSH → SGD), else SGD (sys has SGD, MYR and THB operators; its freezers are HIPL's). The rate is
 * `smart_freezer.claude_recognition.usd_rates`; a currency with no rate there is shown in US dollars.
 */
final class ClaudeCostMoney
{
    private const SYMBOLS = ['SGD' => 'S$', 'IDR' => 'Rp', 'MYR' => 'RM', 'USD' => 'US$'];

    public static function currency(): string
    {
        $configured = strtoupper(trim((string) config('smart_freezer.claude_recognition.currency')));
        $currency = $configured !== '' ? $configured : Cache::remember('claude-freezer-ai-currency', 3600, function () {
            $currencies = DB::table('operators')->join('countries', 'countries.id', '=', 'operators.country_id')
                ->whereNotNull('countries.currency_name')->distinct()->pluck('countries.currency_name');

            return $currencies->count() === 1 ? strtoupper((string) $currencies->first()) : 'SGD';
        });

        return isset(self::rates()[$currency]) ? $currency : 'USD';
    }

    /** The cost in the site's currency, as a number (whole rupiah; cents elsewhere, to 3 places for a tiny cost). */
    public static function amount(?int $usdMicros): ?float
    {
        if ($usdMicros === null) {
            return null;
        }
        $value = $usdMicros / 1_000_000 * self::rates()[self::currency()];

        return self::wholeUnits() ? round($value) : round($value, $value > 0 && $value < 0.1 ? 3 : 2);
    }

    /** "S$0.18", "S$0.005", "Rp 2,544", "RM 0.57"; null when there is no cost. */
    public static function text(?int $usdMicros): ?string
    {
        $value = self::amount($usdMicros);
        if ($value === null) {
            return null;
        }
        $currency = self::currency();
        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';
        if (self::wholeUnits()) {
            return $symbol.' '.number_format($value);
        }
        $decimals = $value > 0 && $value < 0.1 ? 3 : 2;

        return $symbol.number_format($value, $decimals);
    }

    private static function wholeUnits(): bool
    {
        return self::currency() === 'IDR';
    }

    /** @return array<string, float> currency => units per US dollar */
    private static function rates(): array
    {
        return ['USD' => 1.0] + array_map('floatval', (array) config('smart_freezer.claude_recognition.usd_rates'));
    }
}
