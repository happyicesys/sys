<?php

namespace App\Contracts\CardSettlement;

use App\Support\CardSettlement\PayoutTerms;

/**
 * Maps one acquirer's card-type columns onto its payout schedule.
 *
 * Sibling of SettlementReportParser: the parser says what a line IS, this says
 * how that line gets paid. One implementation per acquirer, registered under
 * `card_settlement.payout_terms_resolvers`; an acquirer with no resolver shows
 * no settlement date at all, which is the correct answer for a rail whose terms
 * mark1 has not been told (Midtrans and any non-Singapore gateway today).
 */
interface PayoutTermsResolver
{
    /** Provider key this resolver answers for — matches card_settlement_reports.provider. */
    public function provider(): string;

    /**
     * @param  string|null  $product  the report's product / scheme column
     * @param  string|null  $cardIssuer  the report's issuer column, when it has one
     * @return PayoutTerms|null null when this card type has no mapped schedule
     */
    public function resolve(?string $product, ?string $cardIssuer): ?PayoutTerms;
}
