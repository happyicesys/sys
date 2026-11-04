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

    /*
    | Midtrans has its own switch (same three words), so IDN can enforce it without touching Omise, and
    | sys's `enforce` does not apply to it before its traffic has been seen. Each Midtrans notification
    | carries a signature checked locally, so enforcing adds no API call.
    */
    'midtrans_webhook_verification' => env('PAYMENT_WEBHOOK_VERIFICATION_MIDTRANS', 'log'),

    /*
    | payment:reconcile-midtrans (2026-11-04): asks Midtrans about QRs that expired with no final
    | notification. Never dispenses. Off until PAYMENT_WEBHOOK_VERIFICATION_MIDTRANS=enforce and a dry
    | run has been read. Only rows created in the last `days` are asked about on schedule.
    */
    'midtrans_reconcile' => [
        'enabled' => (bool) env('MIDTRANS_RECONCILE_ENABLED', false),
        'days' => (int) env('MIDTRANS_RECONCILE_DAYS', 90),
        'limit' => (int) env('MIDTRANS_RECONCILE_LIMIT', 300),
        'timeout' => (int) env('MIDTRANS_RECONCILE_TIMEOUT', 8),
    ],

    /* Seconds allowed for the verification read of GET /charges/{id}. */
    'webhook_verify_timeout' => (int) env('PAYMENT_WEBHOOK_VERIFY_TIMEOUT', 8),

    /*
    | Fallback QR reader for gateway QR images the PHP decoder cannot read: Omise's PayNow QRs
    | carry a logo, and since 2026-10-07 a lower-version subset (mostly whole-dollar amounts)
    | failed every time, sending machines an unpayable QR (~302 customers in 3 days). Python with
    | zxing-cpp in its own venv (see resources/scripts/qr_decode.py); missing = no fallback.
    */
    'qr_decoder_python' => env('QR_DECODER_PYTHON', '/home/forge/.local/qrdecode/bin/python'),

    /*
    | Machine payment menu push (Brian, 2026-10-30): after the QR menu changes (Operator → Edit → Machine Payment
    | Menu, or a payment method / merchant / gateway edit), mark1 sends PAYMENTMENU to the machines it concerns and
    | they re-read their wallet list at once. Big-board app 321 is the first to act on the frame; older apps ignore
    | it and pick the change up at their next restart. Off = changes wait for a restart, as before.
    */
    'menu_push' => (bool) env('PAYMENT_MENU_PUSH', true),
    'menu_push_min_vending_apk' => (int) env('PAYMENT_MENU_PUSH_MIN_VENDING_APK', 321),

];
