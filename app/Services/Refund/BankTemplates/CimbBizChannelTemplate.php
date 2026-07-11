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

        $totalCents = (int) $tickets->sum('payout_amount_cents');
        $count = $tickets->count();

        // Originating account resolution.
        //  - New settlement flow: the RefundSettlementService resolves the account
        //    from the settlement's payout group (or operator) and passes it in as
        //    meta['originating_account'] — no silent config fallback, so a
        //    third-party operator can never ride HIPL's env account.
        //  - Legacy /refunds direct export: unchanged — operator field, then config.
        if (!empty($meta['originating_account']['no'])) {
            $accountNo = trim((string) $meta['originating_account']['no']);
            $accountName = trim((string) ($meta['originating_account']['name'] ?? ''));
        } else {
            $operator = $meta['operator'] ?? null;
            $accountNo = trim((string) ($operator?->bank_account_no ?: ($cfg['account_no'] ?? '')));
            $accountName = trim((string) ($operator?->bank_account_name ?: ($cfg['account_name'] ?? '') ?: ($operator?->name ?? '')));
        }

        if ($accountNo === '') {
            throw new \RuntimeException('No originating bank account number set. Fill in "Bank Account No." on the Operator page (or set REFUND_CIMB_ACCOUNT_NO).');
        }

        // ---- header record ----
        $header = implode($d, [
            $cfg['service_code'] ?? '2',
            $this->clean($accountNo),
            $this->clean($accountName),
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
                number_format($t->payout_amount_cents / 100, 2, '.', ''), // C amount (admin final override, else claim)
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
        // Prefer the name the claimant keyed in on the /refund form. Fall back to
        // the email local-part, then the reference, if no name was captured.
        // CIMB benef name is space-stripped ("Ayaan Agarwal" -> "AyaanAgarwal").
        $name = trim((string) $t->contact_name);
        if ($name !== '') {
            return $this->stripSpaces($name);
        }

        if ($t->contact_email && str_contains($t->contact_email, '@')) {
            return strtok($t->contact_email, '@');
        }

        return $t->reference;
    }

    /** Remove all whitespace from a beneficiary name. */
    protected function stripSpaces(string $v): string
    {
        return (string) preg_replace('/\s+/u', '', $v);
    }

    /** Strip the delimiter / newlines from any field value. */
    protected function clean(?string $v): string
    {
        return str_replace(['%', "\n", "\r"], ' ', (string) $v);
    }
}
