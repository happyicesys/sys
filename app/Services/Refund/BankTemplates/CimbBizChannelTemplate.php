<?php

namespace App\Services\Refund\BankTemplates;

use App\Models\RefundTicket;
use Illuminate\Support\Collection;

/**
 * CIMB BizChannel@CIMB bulk payment file.
 *
 * Output is a delimiter-separated text file (delimiter defaults to "%"):
 *
 *   Header:  serviceCode % accountNo % accountName % currency % total(#0.00)
 *            % count % settlementMode % postingIndicator % date(ddmmyyyy)
 *   Detail:  benefAccount % benefName % amount(0.00) % currency % BIC
 *            % purposeCode % description % %        (trailing delimiter)
 *
 * Static originator fields come from config('refund.banks.cimb').
 */
class CimbBizChannelTemplate implements BankBulkTemplate
{
    public function key(): string
    {
        return 'cimb';
    }

    public function label(): string
    {
        return (string) config('refund.banks.cimb.label', 'CIMB BizChannel Bulk Transaction');
    }

    public function fileExtension(): string
    {
        return (string) config('refund.banks.cimb.extension', 'txt');
    }

    public function generate(Collection $tickets, array $meta = []): string
    {
        $cfg = (array) config('refund.banks.cimb', []);
        $d = $cfg['delimiter'] ?? '%';
        $currency = $cfg['currency'] ?? 'SGD';

        $totalCents = (int) $tickets->sum('claimed_amount_cents');
        $count = $tickets->count();

        // ---- header record ----
        $header = implode($d, [
            $cfg['service_code'] ?? '3',
            $cfg['account_no'] ?? '',
            $cfg['account_name'] ?? '',
            $currency,
            number_format($totalCents / 100, 2, '.', ''),
            $count,
            $cfg['settlement_mode'] ?? 'R',
            $cfg['posting_indicator'] ?? 'C',
            now()->format('dmY'),
        ]);

        $lines = [$header];

        // ---- detail records ----
        foreach ($tickets as $t) {
            $lines[] = implode($d, [
                $this->clean($this->paynowMobile($t->payout_destination)), // A PayNow mobile (+65 E.164)
                $this->clean($this->beneficiaryName($t)),        // B beneficiary name
                number_format($t->claimed_amount_cents / 100, 2, '.', ''), // C amount
                $currency,                                       // D currency
                $cfg['proxy_type'] ?? 'MOB',                     // E proxy type — PayNow mobile only
                $cfg['purpose_code'] ?? 'OTHR',                  // F purpose code
                $this->clean(mb_substr((string) $t->reference, 0, 35)), // G remark to counterparty (<=35)
                '',                                              // H DDA reference (blank for payments)
                $this->clean($t->contact_email),                // I beneficiary email
            ]) . $d; // trailing delimiter (matches template)
        }

        return implode("\n", $lines);
    }

    /** Format a PayNow mobile to +65 E.164; fall back to the raw value if parsing fails. */
    protected function paynowMobile(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        try {
            return (new \Propaganistas\LaravelPhone\PhoneNumber($value, config('refund.paynow_country', 'SG')))->formatE164();
        } catch (\Throwable $e) {
            return $value;
        }
    }

    protected function beneficiaryName(RefundTicket $t): string
    {
        // We don't capture a payee name on the form; PayNow resolves it from the
        // proxy. Use the email local-part as a best-effort label, else the ref.
        if ($t->contact_email && str_contains($t->contact_email, '@')) {
            return strtok($t->contact_email, '@');
        }

        return $t->reference;
    }

    /** Strip the delimiter / newlines from any field value. */
    protected function clean(?string $v): string
    {
        return str_replace(['%', "\n", "\r"], ' ', (string) $v);
    }
}
