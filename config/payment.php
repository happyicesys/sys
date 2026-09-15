<?php

return [

    /*
    | Payment-gateway webhook verification (audit M3-01, 2026-09-15).
    |
    | POST /api/v1/payment-gateway-status/{company} is unauthenticated and, for
    | Omise, unsigned: whatever the body says is what mark1 used to act on. A
    | forged "successful" charge dispensed product; a forged "refund" marked a
    | sale refunded. The verifier re-reads the charge from Omise's own API with
    | the merchant's secret key and compares status / amount / order id.
    |
    |   off     - never verify (pre-2026-09-15 behaviour).
    |   log     - verify AFTER the webhook has been processed, on the `low`
    |             queue, and only log the verdict. Zero effect on approval
    |             latency or on QR sales; use it to prove the check agrees with
    |             real traffic before enforcing.
    |   enforce - verify INLINE before the log is updated. A MISMATCH is
    |             refused (logged, HTTP 200 so the gateway does not retry, no
    |             state change). An UNVERIFIABLE verdict (Omise API down or
    |             slow) is allowed through with a warning: an attacker cannot
    |             take Omise's API down, and refusing would stop every QR sale
    |             during an Omise outage.
    */
    'webhook_verification' => env('PAYMENT_WEBHOOK_VERIFICATION', 'log'),

    /* Seconds allowed for the verification read of GET /charges/{id}. */
    'webhook_verify_timeout' => (int) env('PAYMENT_WEBHOOK_VERIFY_TIMEOUT', 8),

];
