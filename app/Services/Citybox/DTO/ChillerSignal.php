<?php

namespace App\Services\Citybox\DTO;

use App\Exceptions\CityboxApiException;

/**
 * One `signal_strength` reading. Their doc promised a number (`80`); the live answer since
 * 2026-10-15 is a string like "RSRP-好(-89dBm,31asu)": an LTE RSRP in dBm with their quality word.
 * Both are read onto mark1's 5-bar scale, the one every signal pill uses:
 *
 *   RSRP ≥ -80 dBm → 5 · ≥ -90 → 4 · ≥ -100 → 3 · ≥ -110 → 2 · lower → 1
 *   a plain number is taken as 0–100 (their doc's example) → 20 per bar
 *
 * The cut-offs are the usual LTE RSRP bands, and agree with their own words on the fleet
 * (-75/-79 极好, -89 好, -96 中).
 */
final readonly class ChillerSignal
{
    private const QUALITY_EN = ['极好' => 'Excellent', '好' => 'Good', '中' => 'Fair', '差' => 'Poor', '极差' => 'Very poor'];

    public function __construct(
        public int $bars,
        public ?int $rsrpDbm,
        public ?string $quality,
        public string $raw,
    ) {}

    public static function fromApi(mixed $value): self
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $n = max(0, min(100, (float) $value));

            return new self((int) round($n / 20), null, null, (string) $value);
        }
        if (is_string($value) && preg_match('/(-\d{2,3})\s*dBm/i', $value, $m)) {
            $dbm = (int) $m[1];
            $quality = preg_match('/^\s*RSRP\s*-\s*([^(]+)\(/u', $value, $q) ? trim($q[1]) : null;

            return new self(self::barsForRsrp($dbm), $dbm, $quality, $value);
        }

        throw new CityboxApiException('Citybox signal_strength returned an unreadable value: '.json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    public static function barsForRsrp(int $dbm): int
    {
        return match (true) {
            $dbm >= -80 => 5,
            $dbm >= -90 => 4,
            $dbm >= -100 => 3,
            $dbm >= -110 => 2,
            default => 1,
        };
    }

    /** "极好 (Excellent)" — their word, with the English beside it when we know it. */
    public function qualityLabel(): ?string
    {
        if ($this->quality === null) {
            return null;
        }

        return isset(self::QUALITY_EN[$this->quality]) ? $this->quality.' ('.self::QUALITY_EN[$this->quality].')' : $this->quality;
    }
}
