<?php

namespace App\Services\HappyHour;

use Illuminate\Support\Facades\DB;

/**
 * Which "Round promo price up to" steps a campaign may use, decided by its OWNER's currency (operator →
 * country → `currency_name`). Brian, 2026-10-31: rupiah rounds up to Rp 1,000 by default and never shows a
 * decimal; every other currency keeps the cent steps it always had.
 *
 * Steps are stored like every amount, in minor units (×100): Rp 1,000 = 100000. HappyHourPricing rounds UP
 * to the step, so a rupiah step of Rp 100 or more always lands on whole rupiah.
 */
final class HappyHourPriceStep
{
    /** Steps per currency, stored value => label. A currency not listed here uses DEFAULT_STEPS. */
    public const STEPS = [
        'IDR' => [10000 => 'Rp 100', 50000 => 'Rp 500', 100000 => 'Rp 1,000'],
    ];

    public const DEFAULT_STEPS = [1 => '1¢', 5 => '5¢', 10 => '10¢', 50 => '50¢', 100 => '$1'];

    /** The step a new campaign starts with, per currency. */
    public const DEFAULTS = ['IDR' => 100000];

    public const DEFAULT_STEP = 10;

    /** @return array<int, string> step => label */
    public static function steps(?string $currency): array
    {
        return self::STEPS[self::key($currency)] ?? self::DEFAULT_STEPS;
    }

    public static function defaultStep(?string $currency): int
    {
        return self::DEFAULTS[self::key($currency)] ?? self::DEFAULT_STEP;
    }

    /** The step itself when the currency offers it, else that currency's default (a stale tab, an old row). */
    public static function normalise(?string $currency, mixed $step): int
    {
        $step = is_numeric($step) ? (int) $step : 0;

        return array_key_exists($step, self::steps($currency)) ? $step : self::defaultStep($currency);
    }

    /** @return array{currency: string, default: int, options: list<array{value: int, label: string}>} */
    public static function choices(?string $currency): array
    {
        return [
            'currency' => self::key($currency),
            'default' => self::defaultStep($currency),
            'options' => collect(self::steps($currency))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
        ];
    }

    /** The operator's currency code ("IDR"), '' when the operator or its country is unknown. */
    public static function currencyOfOperator(?int $operatorId): string
    {
        return $operatorId ? (self::currenciesOfOperators([$operatorId])[$operatorId] ?? '') : '';
    }

    /**
     * @param  array<int, int|null>  $operatorIds
     * @return array<int, string> operator id => currency code
     */
    public static function currenciesOfOperators(array $operatorIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $operatorIds))));
        if ($ids === []) {
            return [];
        }

        return DB::table('operators')
            ->join('countries', 'countries.id', '=', 'operators.country_id')
            ->whereIn('operators.id', $ids)
            ->pluck('countries.currency_name', 'operators.id')
            ->map(fn ($code) => self::key($code))
            ->all();
    }

    private static function key(?string $currency): string
    {
        return strtoupper(trim((string) $currency));
    }
}
