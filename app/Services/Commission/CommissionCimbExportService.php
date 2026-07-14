<?php

namespace App\Services\Commission;

use App\Models\CustomerPeriodSummary;
use App\Services\Banking\CimbBulkPaymentFile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Site commission (location fee) payout file — CIMB BizChannel bulk txt,
 * generated from Site Summary batch-selected rows.
 *
 * Eligibility mirrors the batch "Mark Paid" gate exactly (locked + unpaid +
 * past month + period 2026-05 onward), so the numbers exported are the FROZEN
 * per-period figures the page displays — never provisional live derivations.
 *
 * Amount per site = Net Loc Fee = location_fees_cents − external_subsidize_cents,
 * summed across machine-split segments of the same customer + month (matches
 * the settlement ledger accrual and the batch-paid credit). Zero/negative
 * (Free / Subsidized / we-receive) rows are skipped.
 *
 * Detail rows are account-number + BIC transfers using the site's Bank
 * Details (Site Edit page); the BIC comes from banks.bic_code.
 *
 * Multi-operator selections ARE allowed: the CIMB header carries exactly one
 * originating account, so the export produces ONE FILE PER OPERATOR (header =
 * that operator's bank account from the Operator page, env fallback). A
 * single-operator selection downloads a plain .txt; multiple operators come
 * back as a .zip bundling each operator's txt. Export has NO side effects —
 * re-export freely, then Verify Paid after the bank run succeeds.
 */
class CommissionCimbExportService
{
    /**
     * @param  int[]  $summaryIds  customer_period_summaries ids
     * @return array{filename:string, content:string, mime:string, count:int, total_cents:int}
     *
     * @throws \RuntimeException on any business-rule rejection (readable message)
     */
    public function export(array $summaryIds): array
    {
        $cfg = (array) config('banking.commission.cimb', []);

        $summaries = CustomerPeriodSummary::query()
            ->whereIn('id', $summaryIds)
            // Customer carries OperatorCustomerFilterScope — bypass it here so
            // a payee is never silently dropped from the eager load.
            ->with([
                'customer' => fn ($q) => $q->withoutGlobalScopes()
                    ->select(['id', 'name', 'bank_id', 'bank_account_name', 'bank_account_number', 'report_email']),
                'customer.bank:id,name,bic_code,proxy_type',
            ])
            ->get();

        // Same gate as batchMarkPaidCustomerPeriodSummaries / Summary.vue's
        // isPaidEligibleRow — stale or ineligible rows are silently dropped
        // (the server is the authority; the UI only offers eligible rows).
        $eligible = $summaries->filter(function ($s) {
            if ($s->locked_at === null || $s->paid_at !== null) {
                return false;
            }
            if ($s->is_current_month) {
                return false;
            }

            return !($s->year_month && $s->year_month->toDateString() < '2026-05-01');
        });

        if ($eligible->isEmpty()) {
            throw new \RuntimeException('No eligible rows selected (must be locked, unpaid, past-month, period 2605 onward).');
        }

        // One CIMB file per operator — the header holds a single originating
        // account, so each operator's rows get their own file.
        $byOperator = $eligible->groupBy(fn ($s) => (int) $s->operator_id);

        $operators = \App\Models\Operator::withoutGlobalScopes()
            ->whereIn('id', $byOperator->keys()->filter())
            ->get()
            ->keyBy('id');

        $problems = [];
        $skippedNoOwe = 0;
        $months = [];
        $files = [];        // filename => content
        $count = 0;
        $totalCents = 0;
        $stamp = now()->format('Ymd-Hi');

        foreach ($byOperator as $operatorId => $rows) {
            $operator = $operators->get((int) $operatorId);
            $operatorLabel = $operator?->name ?: ('Operator #' . $operatorId);

            $accountNo = trim((string) ($operator?->bank_account_no ?: ($cfg['account_no'] ?? '')));
            $accountName = trim((string) ($operator?->bank_account_name ?: ($cfg['account_name'] ?? '') ?: ($operator?->name ?? '')));
            if ($accountNo === '') {
                $problems[] = $operatorLabel . ' — no originating bank account (Operator page "Bank Account No." or COMMISSION_CIMB_ACCOUNT_NO)';
                continue;
            }

            $file = (new CimbBulkPaymentFile($cfg))->from($accountNo, $accountName);
            $fileMonths = $this->addRows($file, $rows, $cfg, $problems, $skippedNoOwe);

            if ($file->count() === 0) {
                continue;   // nothing payable for this operator (all F/S/zero).
            }

            $months += $fileMonths;
            $count += $file->count();
            $totalCents += $file->totalCents();

            $slug = Str::slug($operatorLabel) ?: ('operator-' . $operatorId);
            $files['commission-cimb-' . $slug . '-' . implode('-', array_keys($fileMonths)) . '-' . $stamp
                . '.' . ($cfg['extension'] ?? 'txt')] = $file->render();
        }

        // Incomplete bank details block the WHOLE export — a silently missing
        // payee is worse than a rejected file. Fix the listed items and retry.
        if ($problems) {
            throw new \RuntimeException("Missing payment details — no file generated:\n• " . implode("\n• ", $problems));
        }

        if (!$files) {
            throw new \RuntimeException(
                $skippedNoOwe > 0
                    ? 'All selected rows have zero/negative Net Loc Fee (Free / Subsidized) — nothing to pay.'
                    : 'No payable rows found in the selection.'
            );
        }

        // Single operator → plain txt, unchanged. Multiple operators → zip of
        // one txt per operator (each must be uploaded to CIMB separately).
        if (count($files) === 1) {
            return [
                'filename' => array_key_first($files),
                'content' => reset($files),
                'mime' => 'text/plain; charset=UTF-8',
                'count' => $count,
                'total_cents' => $totalCents,
            ];
        }

        return [
            'filename' => 'commission-cimb-' . implode('-', array_keys($months)) . '-' . $stamp . '.zip',
            'content' => $this->zip($files),
            'mime' => 'application/zip',
            'count' => $count,
            'total_cents' => $totalCents,
        ];
    }

    /**
     * Add one payment row per site+month for this operator's summary rows.
     * Returns the months touched ([ym => true]); appends human-readable
     * issues to $problems and bumps $skippedNoOwe for F/S/zero rows.
     */
    protected function addRows(CimbBulkPaymentFile $file, Collection $rows, array $cfg, array &$problems, int &$skippedNoOwe): array
    {
        $months = [];

        // Group machine-split segments: one payment per site + month.
        $groups = $rows->groupBy(fn ($s) => $s->customer_id . '|' . ($s->year_month ? $s->year_month->toDateString() : ''));

        foreach ($groups as $group) {
            $first = $group->first();
            $customer = $first->customer;
            $netCents = (int) $group->sum(fn ($s) => (int) $s->location_fees_cents - (int) $s->external_subsidize_cents);

            if ($netCents <= 0) {           // Free / Subsidized / zero / we-receive.
                $skippedNoOwe++;
                continue;
            }

            $siteName = $customer?->name ?: ('Site #' . $first->customer_id);
            $accNo = preg_replace('/[\s\-]+/', '', (string) ($customer?->bank_account_number ?? ''));

            if ($accNo === '') {
                $problems[] = $siteName . ' — no bank account number (Site Edit ▸ Bank Details)';
                continue;
            }

            // CIMB detail column E: the bank BIC (account transfer) or a PayNow
            // proxy type (MOB/NRIC/UEN) — auto-detected from the value for legacy
            // "Paynow" rows without an explicit proxy_type.
            $colE = \App\Services\Banking\CimbBankDirectory::resolveColE(
                $customer?->bank?->bic_code,
                $customer?->bank?->proxy_type,
                $accNo
            );
            if ($colE === '') {
                $problems[] = $siteName . ' — bank ' . ($customer?->bank?->name ? '"' . $customer->bank->name . '" has no BIC / PayNow proxy type (Data Management ▸ Banks)' : 'not selected (Site Edit ▸ Bank Details)');
                continue;
            }

            $month = $first->year_month ? Carbon::parse($first->year_month) : null;
            if ($month) {
                $months[$month->format('ym')] = true;
            }

            $file->addRow(
                $accNo,
                (string) ($customer?->bank_account_name ?: $siteName),
                $netCents,
                $colE,
                (string) ($cfg['purpose_code'] ?? 'COMC'),
                trim(($cfg['description_prefix'] ?? 'Loc fees') . ($month ? ' ' . $month->format('M Y') : '')),
                (string) ($customer?->report_email ?? '')
            );
        }

        return $months;
    }

    /** Bundle [filename => content] into an in-memory zip (returns raw bytes). */
    protected function zip(array $files): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Multi-operator export needs the PHP zip extension (ext-zip) — or export each operator separately.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cimb');

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create the zip archive.');
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
