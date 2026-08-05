<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product access — exempt operators
    |--------------------------------------------------------------------------
    |
    | Operator ids listed here can never be capped by the "Access Product(s)"
    | allow-list, whatever their operator_product rows say. Users under them
    | can still carry their own personal restriction.
    |
    | Intended as an escape hatch for the HappyIce superuser group (id 1) if a
    | restriction is ever bound there by mistake. Empty by default so the
    | feature behaves exactly as configured in the UI.
    |
    */

    'product_unrestricted_operator_ids' => array_values(array_filter(array_map(
        'intval',
        array_filter(explode(',', (string) env('PRODUCT_ACCESS_UNRESTRICTED_OPERATOR_IDS', '')))
    ))),

];
