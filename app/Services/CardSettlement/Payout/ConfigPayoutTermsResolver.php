<?php

namespace App\Services\CardSettlement\Payout;

use App\Contracts\CardSettlement\PayoutTermsResolver;
use App\Support\CardSettlement\PayoutTerms;

/**
 * A PayoutTermsResolver whose schedule is a table in config rather than code.
 *
 * Every acquirer's schedule has the same shape — a product (and sometimes an
 * issuer) picks a gateway, a term and an MDR — so a new acquirer is a subclass
 * naming its provider key plus a `card_settlement.payout_terms.<key>` block.
 * Only an acquirer whose rules cannot be expressed as product/issuer needs to
 * implement the interface directly.
 *
 * Rules are evaluated TOP-DOWN, first fit wins: a rule listing issuers must sit
 * above the catch-all for the same product, which is why the config comments
 * say so out loud.
 */
abstract class ConfigPayoutTermsResolver implements PayoutTermsResolver
{
    public function resolve(?string $product, ?string $cardIssuer): ?PayoutTerms
    {
        $product = trim((string) $product);
        if ($product === '') {
            return null;
        }

        foreach ($this->rules() as $rule) {
            if (! $this->matchesProduct($rule, $product)) {
                continue;
            }
            if (! $this->matchesIssuer($rule, $cardIssuer)) {
                continue;
            }

            return new PayoutTerms(
                gateway: (string) $rule['gateway'],
                termDays: (int) $rule['term_days'],
                methodLabel: $rule['method'] ?? null,
                mdrRate: $rule['mdr'] ?? null,
                mdrNote: $rule['mdr_note'] ?? null,
            );
        }

        return null;
    }

    /** @return array<int,array<string,mixed>> */
    protected function rules(): array
    {
        return config('card_settlement.payout_terms.'.$this->provider(), []);
    }

    protected function matchesProduct(array $rule, string $product): bool
    {
        return $this->listed($rule['products'] ?? $rule['product'] ?? null, $product);
    }

    /** A rule with no `issuers` key covers every issuer of its product. */
    protected function matchesIssuer(array $rule, ?string $cardIssuer): bool
    {
        if (! isset($rule['issuers'])) {
            return true;
        }

        return $this->listed($rule['issuers'], trim((string) $cardIssuer));
    }

    /**
     * Acquirer files are inconsistent about case and padding ("EFTPOS" vs
     * "EftPos"), so compare case-insensitively on trimmed values. A rule may
     * give one string or a list.
     */
    protected function listed(string|array|null $allowed, string $value): bool
    {
        if ($allowed === null || $value === '') {
            return false;
        }

        foreach ((array) $allowed as $candidate) {
            if (strcasecmp(trim((string) $candidate), $value) === 0) {
                return true;
            }
        }

        return false;
    }
}
