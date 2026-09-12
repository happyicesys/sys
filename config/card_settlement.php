<?php

use App\Services\CardSettlement\Parsers\NetsMerchantConnectParser;
use App\Services\CardSettlement\Payout\NetsPayoutTermsResolver;

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
     * Second, wider pass (Part 2): a line still unmatched after the window
     * above is paired with an unclaimed card sale on the same machine, same
     * cents, within this many seconds either way — but ONLY when the pairing
     * is unique both ways. Measured 2026-09-08: 16 % of "dispensed, no line"
     * card sales had their line sitting just outside the 60/300 s window
     * (machine clock drift). Wide + unique never steals a neighbour's line.
     */
    'match_wide_window_seconds' => 1800,

    /*
     * Card Terminal Companies (lower-cased `card_terminals.name`) whose sales
     * the NETS MerchantConnect file only PARTLY carries (Nets-Auresys: 40–60 %
     * coverage, 2026-09-08). For their terminals "no line" proves nothing: the
     * reconciler records `uncovered`, never `not_captured`, and never ticks a
     * refund from the absence of a line.
     */
    'report_coverage_gap_companies' => ['nets-auresys'],

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

    /*
     * ---------------------------------------------------------------------
     * Settlement (payout) date — "when does this line's money reach the bank"
     * ---------------------------------------------------------------------
     *
     * An acquirer line settles T+N banking days after ITS OWN transaction
     * date, through one of several payment gateways, at a card-type-specific
     * MDR. The whole thing is derived (never stored): the report line already
     * carries the transaction date, the product and the card issuer, so a
     * corrected rule here applies to every historical row at once.
     *
     * One resolver per acquirer, exactly like `providers` above — another
     * acquirer (Midtrans, a Malaysian gateway, …) is one class plus one
     * `payout_terms` block, and a provider with no entry simply shows no
     * settlement date rather than borrowing NETS's schedule.
     */
    'payout_terms_resolvers' => [
        'nets' => NetsPayoutTermsResolver::class,
    ],

    /*
     * T+N is counted in BANKING days (Brian, 2026-09-12): weekends and
     * Singapore public holidays are skipped, and a term that lands on a
     * non-banking day rolls forward to the next one. Holidays come from
     * `holiday_days` where is_public = 1 (populated 2020-2027; beyond that the
     * calendar degrades to weekends only, which is visible as an off-by-a-day
     * around a future holiday rather than a wrong month).
     *
     * Singapore-only, which is all NETS is. An acquirer in another country
     * needs its own holiday source before its terms can be trusted.
     */
    'payout_calendar' => [
        'skip_weekends' => true,
        'skip_public_holidays' => true,
    ],

    /*
     * NETS payout schedule, from the NETS settlement standard Brian supplied
     * on 2026-09-12. Rules are evaluated TOP-DOWN and the first one that fits
     * wins, so a narrower rule (an issuer list) must sit above the catch-all
     * for its product.
     *
     *   product / products : report "Product" column, case-insensitive.
     *   issuers            : report "Financial Institution ID"; omit to match
     *                        every issuer of that product.
     *   term_days          : N in T+N, in banking days.
     *   mdr / mdr_note     : display labels for the tooltip — no arithmetic is
     *                        done with them here (fee computation, if it ever
     *                        lands, should read these and not re-type them).
     *
     * A product/issuer pair no rule covers resolves to NULL and the column
     * stays blank: an unmapped card type must not silently inherit a
     * neighbour's payout date.
     */
    'payout_terms' => [
        'nets' => [
            // Visa / Mastercard — the only T+2 family in the live reports.
            [
                'products' => ['Scheme Credit/Debit'],
                'method' => 'Visa / Mastercard',
                'gateway' => 'DBS CARD CENTER',
                'term_days' => 2,
                'mdr' => '2.5%',
                'mdr_note' => 'after deduct MDR, bank in',
            ],
            // NETS and NETS QR. Issuers seen: DBS Card / DBS PayLah /
            // OCBC Card / OCBC PayAnyone / UOB Card / UOB Mighty / HSBC /
            // Maybank / SC / Unknown — one schedule covers them all.
            [
                'products' => ['EFTPOS'],
                'method' => 'NETS / NETS QR',
                'gateway' => 'COS',
                'term_days' => 1,
                'mdr' => '0.8%',
                'mdr_note' => 'full back in',
            ],
            // NETS FlashPay is NETS's own stored-value card; it is not a line
            // in Brian's table, and was placed on the NETS schedule (2026-09-12).
            [
                'products' => ['FLASHPAY'],
                'method' => 'NETS FlashPay',
                'gateway' => 'COS',
                'term_days' => 1,
                'mdr' => '0.8%',
                'mdr_note' => 'full back in',
            ],
            // WeChat cross-border is the one cross-border scheme that settles
            // through POS — must stay ABOVE the CROSS BORDER catch-all.
            [
                'products' => ['CROSS BORDER'],
                'issuers' => ['WeChat Pay'],
                'method' => 'WeChat (cross-border)',
                'gateway' => 'POS',
                'term_days' => 1,
                'mdr' => '0.8%',
                'mdr_note' => 'full back in',
            ],
            // UnionPay and BHIM are Brian's 1.8% line. Alipay+ and the ASEAN QR
            // schemes (PayNet / RINTIS / JALIN / ARTAJASA) are not in his table
            // and were grouped here on his call (2026-09-12) — revisit if a
            // NETS statement ever shows them settling elsewhere.
            [
                'products' => ['CROSS BORDER'],
                'method' => 'UnionPay / BHIM / cross-border QR',
                'gateway' => 'COS',
                'term_days' => 1,
                'mdr' => '1.8%',
                'mdr_note' => 'full back in',
            ],
            // EZ-Link settles through Auresys. It has NEVER appeared as a line
            // in a MerchantConnect file (the EZ-Link TIDs are not NETS TIDs),
            // so this rule exists to record the term, not because it fires.
            [
                'products' => ['EZ-LINK', 'EZLINK', 'EZ LINK'],
                'method' => 'EZ-Link',
                'gateway' => 'AURESYS',
                'term_days' => 2,
                'mdr' => '2% + GST',
                'mdr_note' => 'after deduct MDR, bank in',
            ],
        ],
    ],
];
