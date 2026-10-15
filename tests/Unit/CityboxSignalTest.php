<?php

namespace Tests\Unit;

use App\Exceptions\CityboxApiException;
use App\Services\Citybox\DTO\ChillerSignal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * signal_strength answers "RSRP-好(-89dBm,31asu)" (live, 2026-10-15), not the number its doc shows;
 * both read onto the 5-bar scale.
 */
class CityboxSignalTest extends TestCase
{
    /** The four live readings of 2026-10-15, plus the doc's numeric form. */
    public static function readings(): array
    {
        return [
            'SGC5001 极好' => ['RSRP-极好(-75dBm,31asu)', 5, -75, '极好 (Excellent)'],
            'SGC6003 极好' => ['RSRP-极好(-79dBm,31asu)', 5, -79, '极好 (Excellent)'],
            'SGC6001 好' => ['RSRP-好(-89dBm,31asu)', 4, -89, '好 (Good)'],
            'SGC6002 中' => ['RSRP-中(-96dBm,31asu)', 3, -96, '中 (Fair)'],
            'weak' => ['RSRP-差(-112dBm,20asu)', 1, -112, '差 (Poor)'],
            'doc example 80' => [80, 4, null, null],
            'numeric string' => ['55', 3, null, null],
        ];
    }

    #[DataProvider('readings')]
    public function test_a_reading_lands_on_five_bars(int|string $raw, int $bars, ?int $dbm, ?string $quality): void
    {
        $s = ChillerSignal::fromApi($raw);
        $this->assertSame([$bars, $dbm, $quality], [$s->bars, $s->rsrpDbm, $s->qualityLabel()]);
    }

    public function test_an_unreadable_value_is_refused(): void
    {
        $this->expectException(CityboxApiException::class);
        ChillerSignal::fromApi('n/a');
    }
}
