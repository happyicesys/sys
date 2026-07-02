<?php

namespace App\Services\Banking;

/**
 * Generic CIMB BizChannel@CIMB bulk payment file builder ("%"-delimited txt,
 * column K of the official Excel template). Shared by any feature that needs
 * a CIMB upload file (refund payouts have their own ticket-coupled template;
 * commission payouts use this one).
 *
 *   Header:  serviceCode % accountNo % accountName % currency % total(#0.00)
 *            % count % settlementMode % postingIndicator % date(ddmmyyyy)
 *   Detail:  destination % benefName % amount(0.00) % currency % colE
 *            % purposeCode % description % ddaRef % email %   (trailing "%")
 *
 * Column E ("colE") is either the beneficiary bank's BIC (account transfer)
 * or a PayNow proxy type (MOB/NRIC/UEN/VPA) — see CimbBankDirectory.
 */
class CimbBulkPaymentFile
{
    protected string $delimiter = '%';
    protected string $currency = 'SGD';
    protected string $serviceCode = '2';
    protected string $settlementMode = 'R';
    protected string $postingIndicator = 'C';

    /** @var array<int, array> */
    protected array $rows = [];

    protected string $accountNo = '';
    protected string $accountName = '';

    public function __construct(array $cfg = [])
    {
        $this->delimiter = (string) ($cfg['delimiter'] ?? '%');
        $this->currency = (string) ($cfg['currency'] ?? 'SGD');
        $this->serviceCode = (string) ($cfg['service_code'] ?? '2');
        $this->settlementMode = (string) ($cfg['settlement_mode'] ?? 'R');
        $this->postingIndicator = (string) ($cfg['posting_indicator'] ?? 'C');
    }

    /** Originating (debiting) account — goes into the header record. */
    public function from(string $accountNo, string $accountName): static
    {
        $this->accountNo = trim($accountNo);
        $this->accountName = trim($accountName);

        return $this;
    }

    /**
     * Add one payment detail row.
     *
     * @param string $destination  beneficiary account no / PayNow proxy value
     * @param string $name         beneficiary name
     * @param int    $amountCents  positive amount in cents
     * @param string $colE         BIC code or proxy type (MOB/NRIC/UEN/VPA)
     * @param string $purpose      purpose code (COMC/COLL/SALA/OTHR)
     * @param string $description  remark to counterparty (truncated to 35)
     * @param string $email        optional beneficiary email
     */
    public function addRow(
        string $destination,
        string $name,
        int $amountCents,
        string $colE,
        string $purpose,
        string $description,
        string $email = ''
    ): static {
        $this->rows[] = [$destination, $name, $amountCents, $colE, $purpose, $description, $email];

        return $this;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function totalCents(): int
    {
        return (int) array_sum(array_map(fn ($r) => $r[2], $this->rows));
    }

    public function render(): string
    {
        if ($this->accountNo === '') {
            throw new \RuntimeException('No originating bank account number set for the CIMB file header.');
        }
        if (!$this->rows) {
            throw new \RuntimeException('No payment rows to export.');
        }

        $d = $this->delimiter;

        $header = implode($d, [
            $this->serviceCode,
            $this->clean($this->accountNo),
            $this->clean($this->accountName),
            $this->currency,
            number_format($this->totalCents() / 100, 2, '.', ''),
            $this->count(),
            $this->settlementMode,
            $this->postingIndicator,
            now()->format('dmY'),
        ]);

        $lines = [$header];

        foreach ($this->rows as [$destination, $name, $amountCents, $colE, $purpose, $description, $email]) {
            $lines[] = implode($d, [
                $this->clean($destination),
                $this->clean($name),
                number_format($amountCents / 100, 2, '.', ''),
                $this->currency,
                $this->clean($colE),
                $this->clean($purpose),
                $this->clean(mb_substr($description, 0, 35)),
                '',                       // DDA reference — blank for payments.
                $this->clean($email),
            ]) . $d; // trailing delimiter, matching the official template.
        }

        return implode("\n", $lines);
    }

    /** Strip the delimiter / newlines from any field value. */
    protected function clean(?string $v): string
    {
        return trim(str_replace([$this->delimiter, "\n", "\r"], ' ', (string) $v));
    }
}
