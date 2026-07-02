<?php

return [

    /*
    | Site commission (location fee) payouts — CIMB BizChannel bulk file
    | generated from Site Summary batch selection. Header originator account
    | comes from the operator's bank fields (Operator page); the envs below
    | fall back to the refund CIMB envs so one deployment config feeds both.
    */
    'commission' => [
        'cimb' => [
            'label' => 'CIMB BizChannel Bulk Transaction',
            'extension' => env('COMMISSION_CIMB_EXTENSION', 'txt'),
            'delimiter' => '%',
            'currency' => env('COMMISSION_CIMB_CURRENCY', env('REFUND_CIMB_CURRENCY', 'SGD')),
            // 2 = Bulk Payment.
            'service_code' => env('COMMISSION_CIMB_SERVICE_CODE', env('REFUND_CIMB_SERVICE_CODE', '2')),
            // R = FAST — commission rows are account-number + BIC transfers
            // (not PayNow proxies), so the PayNow modes (F/G) don't apply.
            'settlement_mode' => env('COMMISSION_CIMB_SETTLEMENT_MODE', 'R'),
            'posting_indicator' => env('COMMISSION_CIMB_POSTING_INDICATOR', env('REFUND_CIMB_POSTING_INDICATOR', 'C')),
            // COMC = commercial payment (the template's commission sample rows).
            'purpose_code' => env('COMMISSION_CIMB_PURPOSE_CODE', 'COMC'),
            // Row description prefix; the period month is appended, e.g.
            // "Loc fees Jun 2026". CIMB truncates descriptions at 35 chars.
            'description_prefix' => env('COMMISSION_CIMB_DESCRIPTION_PREFIX', 'Loc fees'),
            // Fallback originator account when the operator row has none.
            'account_no' => env('COMMISSION_CIMB_ACCOUNT_NO', env('REFUND_CIMB_ACCOUNT_NO', '')),
            'account_name' => env('COMMISSION_CIMB_ACCOUNT_NAME', env('REFUND_CIMB_ACCOUNT_NAME', '')),
        ],
    ],

];
