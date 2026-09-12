<?php

namespace App\Services\CardSettlement\Payout;

use App\Contracts\CardSettlement\PayoutTermsResolver;
use App\Support\CardSettlement\SettlementPayout;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * "When does this settlement line's money reach the bank, and through which
 * gateway" — the single entry point for the Settlement Date column.
 *
 * Registry (provider → PayoutTermsResolver) plus the banking calendar. Nothing
 * is stored: the answer is a pure function of the report line's own transaction
 * date and its card type, so a corrected rule or a newly seeded public holiday
 * fixes every historical row without a backfill. If a SQL-level filter on the
 * settlement date is ever wanted, THAT is when a persisted column earns its
 * migration.
 *
 * Returns null — never a guess — when the provider has no resolver or the card
 * type has no mapped rule. A blank cell is the honest answer for a rail whose
 * terms mark1 has not been told.
 */
class SettlementPayoutResolver
{
    /** @var array<string,PayoutTermsResolver|null> memo, one entry per provider seen. */
    protected array $resolvers = [];

    public function __construct(protected BankingCalendar $calendar) {}

    /**
     * A page of card sales is one provider repeated, so resolve each provider's
     * terms resolver once rather than per row.
     *
     * A configured class that is not a PayoutTermsResolver is a deploy-time
     * mistake and throws, like ParserRegistry: silently blanking the column
     * would hide the misconfiguration behind a plausible-looking empty cell.
     */
    public function resolverFor(string $provider): ?PayoutTermsResolver
    {
        if (array_key_exists($provider, $this->resolvers)) {
            return $this->resolvers[$provider];
        }

        $class = config("card_settlement.payout_terms_resolvers.{$provider}");
        $resolver = $class ? app($class) : null;

        if ($resolver !== null && ! $resolver instanceof PayoutTermsResolver) {
            throw new InvalidArgumentException(
                "Configured payout terms resolver for \"{$provider}\" is not a PayoutTermsResolver."
            );
        }

        return $this->resolvers[$provider] = $resolver;
    }

    public function for(
        ?string $provider,
        ?string $product,
        ?string $cardIssuer,
        CarbonInterface|string|null $transactionDate,
    ): ?SettlementPayout {
        if (blank($provider) || blank($transactionDate)) {
            return null;
        }

        $terms = $this->resolverFor($provider)?->resolve($product, $cardIssuer);
        if ($terms === null) {
            return null;
        }

        // The LINE's transaction date, never the file's cutover date: the NETS
        // business day cuts over ~22:30, so one file spans two calendar dates
        // and its late rows settle a day after its early ones.
        $from = CarbonImmutable::parse($transactionDate)->startOfDay();

        return new SettlementPayout(
            date: $this->calendar->addBankingDays($from, $terms->termDays),
            transactionDate: $from,
            terms: $terms,
            scheme: $this->scheme($product, $cardIssuer),
        );
    }

    protected function scheme(?string $product, ?string $cardIssuer): ?string
    {
        $parts = array_values(array_filter([
            trim((string) $product),
            trim((string) $cardIssuer),
        ], fn ($part) => $part !== ''));

        return $parts === [] ? null : implode(' / ', $parts);
    }
}
