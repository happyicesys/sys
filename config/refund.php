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
    | PayNow bulk-transfer CSV column order. Adjust to your bank's spec.
    */
    'paynow_csv_columns' => ['reference', 'payout_destination', 'amount', 'contact_email'],

];
