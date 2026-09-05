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

    /*
     * Where uploaded report files live. Private object storage (DO Spaces,
     * S3-compatible) by default — never the app's public disk — served only
     * through the authed /card-settlements/{id}/download route. Falls back to
     * 'local' when the Spaces credentials are absent (dev / test), see
     * CardSettlementReport::storageDisk().
     */
    'storage_disk' => env('CARD_SETTLEMENT_DISK', 'digitaloceanspaces'),
    'storage_folder' => 'card-settlements',

    // Max upload size (KB) for a settlement report file.
    'max_upload_kb' => 20480,

    /*
     * Card Terminal Company (`card_terminals.name`, lower-cased) → the
     * `card_terminal_bindings.provider` value a binding for one of its
     * terminals must carry. The matcher filters bindings by the REPORT's
     * provider, so this mapping is what decides whether a terminal's sales can
     * ever be reconciled.
     *
     * Nets-Auresys is a NETS terminal behind an Auresys front end: its 15 TIDs
     * appear on the same NETS MerchantConnect report and have always been
     * stored as 'nets' — keep them there or those machines stop matching.
     *
     * A company with no entry gets a slug of its own name (Nayax → 'nayax'),
     * which deliberately matches no report: there is no parser for it, so its
     * sales are simply never reconciled rather than being mis-assigned to NETS.
     */
    'company_provider' => [
        'nets' => 'nets',
        'nets-auresys' => 'nets',
    ],

    // Provider for a terminal with no company set at all. 'nets' is the
    // historical column default and what all 312 pre-2026-09-05 rows carry.
    'default_provider' => 'nets',
];
