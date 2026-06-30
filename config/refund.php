<?php

return [

    /*
    | Master switch for customer-facing emails. When false (default) the
    | RefundEmailService logs the intended send instead of delivering, so
    | nothing reaches real customers until you flip REFUND_EMAIL_ENABLED=true.
    */
    'email_enabled' => env('REFUND_EMAIL_ENABLED', false),

    /*
    | Reference prefix for tickets (RFD-000123) and payout batches.
    */
    'reference_prefix' => env('REFUND_REFERENCE_PREFIX', 'RFD'),
    'batch_reference_prefix' => env('REFUND_BATCH_REFERENCE_PREFIX', 'BATCH'),

    /*
    | Candidate matching.
    | - days: which "when did you buy" options are allowed.
    | - amount_tolerance_cents: 0 = exact match on the entered amount.
    | - max_candidates: cap returned to the form.
    */
    'match' => [
        'days' => ['today', 'yesterday'],
        // How far back a customer may pick a custom "Another date". 0 disables the
        // custom date picker entirely (only Today/Yesterday). Acts as the refund
        // eligibility window — dates older than this fall through to manual review.
        'max_lookback_days' => env('REFUND_MAX_LOOKBACK_DAYS', 14),
        'amount_tolerance_cents' => env('REFUND_AMOUNT_TOLERANCE_CENTS', 0),
        'max_candidates' => env('REFUND_MAX_CANDIDATES', 12),
    ],

    /*
    | Card terminals (vend_transactions.cashless_mfg / Vend::CARD_TERMINALS) that
    | refund automatically via the processor (no manual payout needed). Only
    | Nayax per current business rule — other POS (Nets/PAX/MLS) need manual payout.
    */
    'auto_refund_terminals' => ['Nayax'],

    /*
    | Fallback ISO country (e.g. SG, MY, ID) for PayNow phone validation when a
    | machine's operator has no country set. Per-country instances can override.
    */
    'default_country' => env('REFUND_DEFAULT_COUNTRY', 'SG'),

    /*
    | PayNow is Singapore-only, so refund mobile numbers are validated and
    | formatted (+65 E.164) against this country.
    */
    'paynow_country' => env('REFUND_PAYNOW_COUNTRY', 'SG'),

    /*
    | PayNow bulk-transfer CSV column order (generic fallback export).
    */
    'paynow_csv_columns' => ['reference', 'payout_destination', 'amount', 'contact_email'],

    /*
    | Bank bulk-transfer templates. Each key maps to a generator class
    | (see app/Services/Refund/BankTemplates). Add more banks here later.
    */
    'default_bank' => env('REFUND_DEFAULT_BANK', 'cimb'),

    'banks' => [
        'cimb' => [
            'label' => 'CIMB BizChannel Bulk Transaction',
            'extension' => env('REFUND_CIMB_EXTENSION', 'csv'), // CIMB %-delimited content; .csv or .txt both import
            'delimiter' => '%',
            // --- originator (your company) header fields — set per deployment ---
            'service_code' => env('REFUND_CIMB_SERVICE_CODE', '2'),         // 1=Giro Collection, 2=Bulk Payment, 3=Payroll
            'account_no' => env('REFUND_CIMB_ACCOUNT_NO', ''),             // 10-digit CIMB source account
            'account_name' => env('REFUND_CIMB_ACCOUNT_NAME', ''),
            'currency' => env('REFUND_CIMB_CURRENCY', 'SGD'),
            'settlement_mode' => env('REFUND_CIMB_SETTLEMENT_MODE', 'F'),  // B=GIRO, R=FAST, F=PayNow FAST, G=PayNow GIRO
            'posting_indicator' => env('REFUND_CIMB_POSTING_INDICATOR', 'C'), // C=Consolidated, I=Individual
            'purpose_code' => env('REFUND_CIMB_PURPOSE_CODE', 'OTHR'),     // COLL/COMC/SALA/OTHR
            // We only refund via PayNow to a personal mobile number, so column E is always MOB.
            'proxy_type' => env('REFUND_CIMB_PROXY_TYPE', 'MOB'),
        ],
    ],

    /*
    | Customer attachments (images or short videos). max_kb default 30 MB.
    | NOTE: PHP upload_max_filesize / post_max_size (and nginx client_max_body_size)
    | must be >= this for large videos to upload successfully.
    */
    'attachments' => [
        'max_count' => env('REFUND_ATTACHMENT_MAX_COUNT', 3),
        'max_kb' => env('REFUND_ATTACHMENT_MAX_KB', 30720), // 30 MB
    ],

];
