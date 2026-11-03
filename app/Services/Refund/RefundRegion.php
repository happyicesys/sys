<?php

namespace App\Services\Refund;

use App\Models\RefundTicket;
use App\Models\Vend;
use App\Services\I18n\TranslationCatalog;
use Illuminate\Support\Facades\DB;

/**
 * What a refund looks like where the machine stands: its money, the ways we pay back and the language
 * its customers read (Brian, 2026-11-03: the form asked Indonesian customers for a Singapore PayNow
 * number in "$"). Read from the machine's operator's country; a machine with no operator (8 of 83 on
 * IDN) falls back to the server's own country when every operator on it shares one. Anything else
 * answers as today: "$", two decimals, PayNow + PayPal, the viewer's language.
 *
 * Rupiah carries two units. vend_transactions and refund_tickets store it ×100 like every currency
 * (Rp 20,000 = 2000000), but Midtrans' payment_gateway_logs.amount is whole rupiah (20000.00), where
 * Omise's is cents. gatewayCents() / gatewayAmount() are the only crossing between the two.
 */
final class RefundRegion
{
    /** Where an Indonesian customer can be paid back by hand. Shown as listed; the last one asks which bank. */
    public const LOCAL_PROVIDERS = ['GoPay', 'OVO', 'DANA', 'ShopeePay', 'LinkAja', 'BCA', 'Mandiri', 'BNI', 'BRI', 'CIMB Niaga'];

    public const OTHER_PROVIDER = 'Other bank';

    private function __construct(
        private readonly string $currency,
        private readonly string $symbol,
        private readonly bool $wholeUnits,
    ) {}

    public static function forVend(?Vend $vend): self
    {
        $country = $vend?->operator_id
            ? DB::table('operators')->join('countries', 'countries.id', '=', 'operators.country_id')
                ->where('operators.id', $vend->operator_id)
                ->first(['countries.currency_name', 'countries.currency_symbol', 'countries.is_currency_exponent_hidden'])
            : null;

        return $country ? self::fromCountry($country) : self::forServer();
    }

    /** The server's own country: the one every operator on it shares, else the default. */
    public static function forServer(): self
    {
        $countryIds = DB::table('operators')->whereNotNull('country_id')->distinct()->pluck('country_id');
        $country = $countryIds->count() === 1
            ? DB::table('countries')->where('id', $countryIds->first())
                ->first(['currency_name', 'currency_symbol', 'is_currency_exponent_hidden'])
            : null;

        return $country ? self::fromCountry($country) : new self('', '$', false);
    }

    private static function fromCountry(object $country): self
    {
        return new self(
            (string) $country->currency_name,
            (string) ($country->currency_symbol ?: '$'),
            (bool) $country->is_currency_exponent_hidden,
        );
    }

    private function isIndonesia(): bool
    {
        return $this->currency === 'IDR';
    }

    /** @return array<int, string> */
    public function methods(): array
    {
        return $this->isIndonesia()
            ? [RefundTicket::METHOD_LOCAL_TRANSFER, RefundTicket::METHOD_PAYPAL]
            : [RefundTicket::METHOD_PAYNOW, RefundTicket::METHOD_PAYPAL];
    }

    /** The language a visitor who is not signed in reads the form in; null = the default. */
    public function customerLocale(): ?string
    {
        return $this->isIndonesia() && array_key_exists('id', TranslationCatalog::LOCALES) ? 'id' : null;
    }

    /** The most the customer form accepts, in major units (a sale reached Rp 320,000 on IDN in 90 days). */
    public function maxAmount(): int
    {
        return $this->wholeUnits ? 10000000 : 100000;
    }

    /** The most a Final Refund Amount may be set to, in major units. */
    public function maxFinalAmount(): float
    {
        return $this->wholeUnits ? 10000000.0 : 99999.99;
    }

    /** A payment_gateway_logs.amount in the ×100 cents everything else stores. */
    public function gatewayCents(mixed $amount): int
    {
        return $this->wholeUnits ? (int) round(((float) $amount) * 100) : (int) $amount;
    }

    /** Cents in the unit of payment_gateway_logs.amount, to search that column. */
    public function gatewayAmount(int $cents): int|float
    {
        return $this->wholeUnits ? $cents / 100 : $cents;
    }

    /** An amount as staff read it in an audit note or a check ("$3.50", "Rp 20,000"). */
    public function label(int $cents): string
    {
        return $this->wholeUnits
            ? $this->symbol.' '.number_format($cents / 100)
            : '$'.number_format($cents / 100, 2);
    }

    /** What the Vue pages need (`utils/refundMoney.js`). */
    public function toArray(): array
    {
        return [
            'symbol' => $this->wholeUnits ? $this->symbol : '$',
            'whole_units' => $this->wholeUnits,
            'max_amount' => $this->maxAmount(),
            'methods' => $this->methods(),
            'providers' => $this->isIndonesia() ? [...self::LOCAL_PROVIDERS, self::OTHER_PROVIDER] : [],
        ];
    }
}
