<?php

namespace App\Contracts\CardSettlement;

use App\Services\CardSettlement\ParsedReport;

interface SettlementReportParser
{
    /** Provider key, matching config('card_settlement.providers'). */
    public function provider(): string;

    /**
     * Parse a settlement report file into a normalized ParsedReport.
     *
     * @throws \App\Services\CardSettlement\SettlementParseException on a file
     *                                                               this parser cannot recognise.
     */
    public function parse(string $path): ParsedReport;
}
