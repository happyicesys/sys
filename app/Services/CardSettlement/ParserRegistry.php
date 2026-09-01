<?php

namespace App\Services\CardSettlement;

use App\Contracts\CardSettlement\SettlementReportParser;
use InvalidArgumentException;

class ParserRegistry
{
    public static function for(string $provider): SettlementReportParser
    {
        $class = config("card_settlement.providers.{$provider}");
        if (! $class) {
            throw new InvalidArgumentException("No settlement parser configured for provider \"{$provider}\".");
        }

        return app($class);
    }

    /** @return string[] provider keys */
    public static function providers(): array
    {
        return array_keys(config('card_settlement.providers', []));
    }
}
