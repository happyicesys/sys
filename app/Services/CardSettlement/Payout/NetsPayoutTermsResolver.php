<?php

namespace App\Services\CardSettlement\Payout;

/**
 * NETS MerchantConnect payout schedule (Brian's NETS settlement standard,
 * 2026-09-12). The rules themselves live in
 * `config/card_settlement.php` → payout_terms.nets, where they can be
 * corrected without a deploy-shaped code change.
 */
class NetsPayoutTermsResolver extends ConfigPayoutTermsResolver
{
    public function provider(): string
    {
        return 'nets';
    }
}
