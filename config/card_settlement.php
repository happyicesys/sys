<?php

use App\Services\CardSettlement\Parsers\NetsMerchantConnectParser;

return [
    /*
     * Parser per provider. Adding another acquirer = one parser class
     * implementing App\Contracts\CardSettlement\SettlementReportParser
     * plus an entry here.
     */
    'providers' => [
        'nets' => NetsMerchantConnectParser::class,
    ],

    /*
     * Matching window, in seconds, for report rows with a full timestamp.
     * The terminal stamps card-approval time; our TRADE frame lands 10–25 s
     * later (measured 2026-08 across the fleet), so the report time normally
     * sits BEFORE the sale's transaction_datetime:
     *   accept when (txn − report) ∈ [-early_slack, +late_slack].
     */
    'match_early_slack_seconds' => 60,
    'match_late_slack_seconds' => 300,

    /*
     * Rows from Excel-damaged files keep only mm:ss (hour lost). They match
     * circularly within the hour using the same slacks; a second candidate in
     * a different hour makes the row AMBIGUOUS instead of guessing.
     */

    // Attachment storage folder for uploaded report files.
    'storage_folder' => 'sys/card-settlements',

    // Max upload size (KB) for a settlement report file.
    'max_upload_kb' => 20480,
];
