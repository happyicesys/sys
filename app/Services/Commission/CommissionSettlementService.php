<?php

namespace App\Services\Commission;

use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementExport;
use App\Models\CommissionSettlementLog;
use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use App\Models\CustomerSettlementLog;
use App\Models\Operator;
use App\Models\PayoutGroup;
use App\Services\Banking\CimbBankDirectory;
use App\Services\Banking\CimbBulkPaymentFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Site Settlement — batches eligible Site Summary rows (location-fee / commission)
 * into dated, per-payout-group pools, mirroring the refund settlement lifecycle
 * (open → closed). Export produces the CIMB file to each site's bank account /
 * PayNow proxy; Mark done sets the existing paid-flags AND posts the
 * customer_settlements ledger credit (same as the on-summary Mark-Paid).
 *
 * See SITE_SETTLEMENT_PLAN.md.
 */
class CommissionSettlementService
{
    /** Paid-tracking cutoff (period 2605 onward), same gate as Site Summary. */
    protected const PAID_CUTOFF = '2026-05-01';

    /**
     * Push selected eligible period-summary rows into the day's OPEN settlement
     * for each row's payout head. A row already paid, in a settlement, unlocked,
     * current-month, before the cutoff, or with no Net Loc Fee is skipped.
     *
     * @return array{pushed:int, settlements:array<string>}
     */
    public function push(array $summaryIds, ?int $userId = null, ?string $actorLabel = 'Admin', ?int $amountCents = null): array
    {
        return DB::transaction(function () use ($summaryIds, $userId, $actorLabel, $amountCents) {
            $rows = CustomerPeriodSummary::query()
                ->whereIn('id', $summaryIds)
                ->whereNotNull('locked_at')
                ->whereNull('paid_at')
                ->whereNull('commission_settlement_id')
                ->where('is_current_month', false)
                ->get()
                ->filter(fn ($s) => $this->eligible($s));

            if ($rows->isEmpty()) {
                throw new \RuntimeException('No eligible rows to push. Rows must be locked, unpaid, past the current month, from period 2605 onward, owe a location fee, and not already in a settlement.');
            }

            $pushed = 0;
            $refs = [];

            foreach ($rows->groupBy('operator_id') as $operatorId => $group) {
                if (!$operatorId) {
                    continue; // no operator → can't resolve a payout account
                }
                $head = $this->headFor((int) $operatorId);
                $settlement = $this->openSettlementFor($head, $userId, $actorLabel);

                foreach ($group as $summary) {
                    // Capture the "Amount paid" from the single Send-to-Settlement
                    // modal (null on batch push → the row keeps the auto Net Loc Fee).
                    $attrs = ['commission_settlement_id' => $settlement->id];
                    if ($amountCents !== null) {
                        $attrs['settlement_amount_cents'] = max(0, $amountCents);
                    }
                    $summary->update($attrs);
                    $this->settlementLog($settlement, 'entry_added', 'Added ' . $this->siteLabel($summary) . ' ' . $this->monthLabel($summary), $userId, $actorLabel, ['summary_id' => $summary->id]);
                    $pushed++;
                    $refs[$settlement->reference] = true;
                }
                $this->recount($settlement);
            }

            if ($pushed === 0) {
                throw new \RuntimeException('Selected rows could not be pushed (missing operator).');
            }

            return ['pushed' => $pushed, 'settlements' => array_keys($refs)];
        });
    }

    public function close(CommissionSettlement $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): void
    {
        if ($settlement->status !== CommissionSettlement::STATUS_OPEN) {
            throw new \RuntimeException('Only an open settlement can be closed.');
        }
        $this->recount($settlement);
        if ((int) $settlement->fresh()->count === 0) {
            throw new \RuntimeException('Cannot close an empty settlement.');
        }
        $settlement->update([
            'status' => CommissionSettlement::STATUS_CLOSED,
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);
        $this->settlementLog($settlement, 'closed', 'Settlement closed', $userId, $actorLabel);
    }

    public function reopen(CommissionSettlement $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): void
    {
        if ($settlement->status === CommissionSettlement::STATUS_OPEN) {
            throw new \RuntimeException('This settlement is already open.');
        }
        $settlement->update([
            'status' => CommissionSettlement::STATUS_OPEN,
            'closed_by' => null,
            'closed_at' => null,
        ]);
        $this->settlementLog($settlement, 'reopened', 'Settlement reopened', $userId, $actorLabel);
    }

    /** Remove a member row (Open only) — unlinks it so it can be pushed again. */
    public function returnToPool(CommissionSettlement $settlement, CustomerPeriodSummary $summary, ?int $userId = null, ?string $actorLabel = 'Admin'): void
    {
        if ($settlement->status !== CommissionSettlement::STATUS_OPEN) {
            throw new \RuntimeException('Rows can only be removed while the settlement is Open.');
        }
        if ((int) $summary->commission_settlement_id !== (int) $settlement->id) {
            throw new \RuntimeException('That row is not in this settlement.');
        }
        if ($summary->paid_at !== null) {
            throw new \RuntimeException('A paid row cannot be removed.');
        }
        DB::transaction(function () use ($settlement, $summary, $userId, $actorLabel) {
            $summary->update(['commission_settlement_id' => null, 'settlement_amount_cents' => null]);
            $this->settlementLog($settlement, 'entry_removed', 'Removed ' . $this->siteLabel($summary) . ' ' . $this->monthLabel($summary), $userId, $actorLabel, ['summary_id' => $summary->id]);
            $this->recount($settlement);
        });
    }

    public function voidEmpty(CommissionSettlement $settlement, ?int $userId = null): void
    {
        if ($settlement->status !== CommissionSettlement::STATUS_OPEN) {
            throw new \RuntimeException('Only an open settlement can be voided.');
        }
        $this->recount($settlement);
        if ((int) $settlement->fresh()->count > 0) {
            throw new \RuntimeException('Settlement is not empty.');
        }
        $settlement->logs()->delete();
        $settlement->delete();
    }

    /**
     * Build the CIMB bulk file for this settlement — one detail row per site+month
     * (sums machine segments), beneficiary = the site's bank account / PayNow proxy,
     * debit header = the payout group's (or operator's) account. Does not change
     * status. Blocks if any site is missing an account / BIC / proxy.
     *
     * @return array{filename:string, content:string, path:string}
     */
    public function exportCimb(CommissionSettlement $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): array
    {
        return DB::transaction(function () use ($settlement, $userId, $actorLabel) {
            // Customer carries OperatorCustomerFilterScope — bypass it so a
            // cross-operator payout-group settlement never drops a payee.
            $rows = $this->members($settlement)->load(['customer' => fn ($q) => $q->withoutGlobalScopes()->with('bank')]);
            if ($rows->isEmpty()) {
                throw new \RuntimeException('No rows to export in this settlement.');
            }

            $cfg = (array) config('banking.commission.cimb', []);
            $account = $this->resolveOriginatingAccount($settlement, $cfg);

            $file = (new CimbBulkPaymentFile($cfg))->from($account['no'], $account['name']);

            $groups = $rows->groupBy(fn ($s) => $s->customer_id . '|' . ($s->year_month ? $s->year_month->toDateString() : ''));
            $problems = [];
            $total = 0;

            foreach ($groups as $group) {
                $first = $group->first();
                $customer = $first->customer;
                $net = (int) $group->sum(fn ($s) => $s->payoutCents());
                if ($net <= 0) {
                    continue;
                }
                $siteName = $customer?->name ?: ('Site #' . $first->customer_id);
                $accNo = preg_replace('/[\s\-]+/', '', (string) ($customer?->bank_account_number ?? ''));
                if ($accNo === '') {
                    $problems[] = $siteName . ' — no bank account number (Site Edit ▸ Bank Details)';
                    continue;
                }
                $colE = CimbBankDirectory::resolveColE($customer?->bank?->bic_code, $customer?->bank?->proxy_type, $accNo);
                if ($colE === '') {
                    $problems[] = $siteName . ' — no BIC / PayNow proxy type on its bank';
                    continue;
                }
                $month = $first->year_month ? Carbon::parse($first->year_month) : null;
                // Remark (CIMB column G): "HI <site ref> <site name ≤17 chars> <Mon+yy>"
                // e.g. "HI 20123 Sube Mediacorp Jun26". Capped at 35 chars overall.
                $siteRef = $first->customer_id + \App\Models\Customer::RUNNING_NUMBER_INIT;
                $remark = mb_substr(trim('HI ' . $siteRef . ' ' . mb_substr($siteName, 0, 17) . ($month ? ' ' . $month->format('My') : '')), 0, 35);
                $file->addRow(
                    $accNo,
                    (string) ($customer?->bank_account_name ?: $siteName),
                    $net,
                    $colE,
                    (string) ($cfg['purpose_code'] ?? 'COMC'),
                    $remark,
                    '' // beneficiary email — not required in the file
                );
                $total += $net;
            }

            if (!empty($problems)) {
                throw new \RuntimeException('Cannot export — fix these sites first: ' . implode('; ', $problems));
            }
            if ($file->count() === 0) {
                throw new \RuntimeException('No payable rows in this settlement.');
            }

            $content = $file->render();
            $filename = $settlement->reference . '-cimb.txt';
            $path = 'commission-payouts/' . $filename;
            Storage::disk('local')->put($path, $content);

            CommissionSettlementExport::create([
                'commission_settlement_id' => $settlement->id,
                'format' => CommissionSettlementExport::FORMAT_CIMB_TXT,
                'file_path' => $path,
                'count' => $file->count(),
                'total_cents' => $total,
                'exported_by' => $userId,
                'exported_at' => now(),
            ]);
            $settlement->update(['exported_by' => $userId, 'exported_at' => now(), 'csv_path' => $path]);
            $this->settlementLog($settlement, 'exported_cimb', 'Exported CIMB file ' . $filename . ' (' . $file->count() . ' sites, $' . number_format($total / 100, 2) . ')', $userId, $actorLabel, ['file' => $path, 'count' => $file->count()]);

            return ['filename' => $filename, 'content' => $content, 'path' => $path];
        });
    }

    /**
     * Mark the selected member rows paid — sets the paid-flags AND posts the
     * customer_settlements ledger credit, exactly like the on-summary batch
     * Mark-Paid. Auto-closes once every member row is paid.
     *
     * @return int number of period rows marked paid
     */
    public function markDone(CommissionSettlement $settlement, array $summaryIds, ?int $userId = null, ?string $actorLabel = 'Admin', ?string $paidDate = null): int
    {
        // Allowed whether open or closed (mirrors the refund settlement) — the
        // settlement auto-closes once every row is paid.
        $paidDate = $paidDate ? Carbon::parse($paidDate)->toDateString() : now()->toDateString();

        return DB::transaction(function () use ($settlement, $summaryIds, $userId, $actorLabel, $paidDate) {
            $rows = CustomerPeriodSummary::query()
                ->where('commission_settlement_id', $settlement->id)
                ->whereIn('id', $summaryIds)
                ->whereNull('paid_at')
                ->get()
                ->filter(fn ($s) => $this->eligible($s));

            if ($rows->isEmpty()) {
                throw new \RuntimeException('None of the selected rows can be marked done (must be in this settlement, locked, unpaid).');
            }

            $now = now();
            $paid = 0;
            foreach ($rows as $summary) {
                $summary->paid_at = $now;
                $summary->paid_date = $paidDate;
                $summary->paid_by = $userId;
                $summary->is_paid = true;
                $summary->save();

                // Ledger credit — mirror batchMarkPaidCustomerPeriodSummaries.
                CustomerSettlement::query()
                    ->where('customer_period_summary_id', $summary->id)
                    ->where('source', CustomerSettlement::SOURCE_PAID_ACTION)
                    ->delete();

                $netLocFee = $summary->payoutCents();
                if ($netLocFee > 0) {
                    $monthLabel = $summary->year_month ? Carbon::parse($summary->year_month)->format('M Y') : '';
                    $entry = CustomerSettlement::create([
                        'customer_id' => $summary->customer_id,
                        'operator_id' => $summary->operator_id,
                        'entry_date' => $paidDate,
                        'year_month' => $summary->year_month,
                        'entry_type' => CustomerSettlement::TYPE_PAYMENT,
                        'amount_cents' => -$netLocFee,
                        'item' => 'Payment' . ($monthLabel ? ' — ' . $monthLabel : ''),
                        'remarks' => null,
                        'customer_period_summary_id' => $summary->id,
                        'source' => CustomerSettlement::SOURCE_PAID_ACTION,
                        'created_by' => $userId,
                    ]);
                    CustomerSettlementLog::create([
                        'customer_id' => $entry->customer_id,
                        'customer_settlement_id' => $entry->id,
                        'reference_no' => $entry->reference_no,
                        'action' => CustomerSettlementLog::ACTION_PAYMENT,
                        'entry_type' => $entry->entry_type,
                        'old_amount_cents' => null,
                        'new_amount_cents' => $netLocFee,
                        'note' => 'Payment recorded' . ($monthLabel ? ' for ' . $monthLabel : '') . ' via settlement ' . $settlement->reference,
                        'changed_by' => $userId,
                        'source' => CustomerSettlement::SOURCE_PAID_ACTION,
                    ]);
                }
                $this->settlementLog($settlement, 'marked_done', 'Marked ' . $this->siteLabel($summary) . ' ' . $this->monthLabel($summary) . ' paid', $userId, $actorLabel, ['summary_id' => $summary->id]);
                $paid++;
            }

            $this->recount($settlement);

            // Auto-close once every member row is paid.
            $remaining = CustomerPeriodSummary::where('commission_settlement_id', $settlement->id)
                ->whereNull('paid_at')->count();
            if ($remaining === 0 && $settlement->status === CommissionSettlement::STATUS_OPEN) {
                $settlement->update(['status' => CommissionSettlement::STATUS_CLOSED, 'closed_by' => $userId, 'closed_at' => now()]);
                $this->settlementLog($settlement, 'closed', 'Auto-closed — all rows paid', $userId, $actorLabel);
            }

            return $paid;
        });
    }

    // ---- internals ----

    protected function eligible(CustomerPeriodSummary $s): bool
    {
        if ($s->locked_at === null || $s->is_current_month) {
            return false;
        }
        if ($s->year_month && $s->year_month->toDateString() < self::PAID_CUTOFF) {
            return false;
        }
        $net = (int) $s->location_fees_cents - (int) $s->external_subsidize_cents;
        return $net > 0;
    }

    protected function headFor(int $operatorId): array
    {
        $operator = Operator::withoutGlobalScopes()->find($operatorId);
        if (!$operator) {
            throw new \RuntimeException("Operator #{$operatorId} not found for settlement grouping.");
        }
        if ($operator->payout_group_id) {
            $group = PayoutGroup::find($operator->payout_group_id);
            if ($group) {
                return ['payout_group_id' => $group->id, 'operator_id' => null, 'code' => $group->code];
            }
        }
        return ['payout_group_id' => null, 'operator_id' => $operator->id, 'code' => $operator->code ?? ('OP' . $operator->id)];
    }

    protected function openSettlementFor(array $head, ?int $userId, ?string $actorLabel): CommissionSettlement
    {
        $date = now()->toDateString();

        $scope = fn () => CommissionSettlement::query()
            ->whereDate('settlement_date', $date)
            ->when($head['payout_group_id'], fn ($w) => $w->where('payout_group_id', $head['payout_group_id']), fn ($w) => $w->whereNull('payout_group_id'))
            ->when($head['operator_id'], fn ($w) => $w->where('operator_id', $head['operator_id']), fn ($w) => $w->whereNull('operator_id'));

        $open = $scope()->where('status', CommissionSettlement::STATUS_OPEN)->lockForUpdate()->first();
        if ($open) {
            return $open;
        }

        $seq = (int) $scope()->max('sequence') + 1;
        $settlement = CommissionSettlement::create([
            'reference' => 'PENDING',
            'settlement_date' => $date,
            'payout_group_id' => $head['payout_group_id'],
            'operator_id' => $head['operator_id'],
            'sequence' => $seq,
            'status' => CommissionSettlement::STATUS_OPEN,
            'created_by' => $userId,
            'count' => 0,
            'total_cents' => 0,
        ]);
        $settlement->reference = 'CST-' . now()->format('ymd') . '-' . strtoupper((string) $head['code']) . '-' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
        $settlement->save();
        $this->settlementLog($settlement, 'created', 'Settlement opened', $userId, $actorLabel);

        return $settlement;
    }

    protected function members(CommissionSettlement $settlement)
    {
        return CustomerPeriodSummary::where('commission_settlement_id', $settlement->id)->get();
    }

    protected function recount(CommissionSettlement $settlement): void
    {
        $rows = $this->members($settlement);
        $groups = $rows->groupBy(fn ($s) => $s->customer_id . '|' . ($s->year_month ? $s->year_month->toDateString() : ''));
        $total = (int) $rows->sum(fn ($s) => $s->payoutCents());
        $settlement->update(['count' => $groups->count(), 'total_cents' => $total]);
    }

    /** @return array{no:string, name:string} */
    protected function resolveOriginatingAccount(CommissionSettlement $settlement, array $cfg): array
    {
        if ($settlement->payout_group_id) {
            $group = PayoutGroup::find($settlement->payout_group_id);
            $no = trim((string) ($group?->bank_account_no ?? ''));
            $name = trim((string) ($group?->bank_account_name ?? ''));
            $label = $group?->name ?? ('payout group #' . $settlement->payout_group_id);
        } else {
            $op = Operator::withoutGlobalScopes()->find($settlement->operator_id);
            $no = trim((string) ($op?->bank_account_no ?? ''));
            $name = trim((string) ($op?->bank_account_name ?: ($op?->name ?? '')));
            $label = $op?->name ?? ('operator #' . $settlement->operator_id);
        }
        if ($no === '') {
            $no = trim((string) ($cfg['account_no'] ?? ''));
            $name = $name !== '' ? $name : trim((string) ($cfg['account_name'] ?? ''));
        }
        if ($no === '') {
            throw new \RuntimeException("No originating CIMB account set for {$label}. Set the bank account on its payout group (or the operator) before exporting.");
        }
        return ['no' => $no, 'name' => $name];
    }

    protected function siteLabel(CustomerPeriodSummary $s): string
    {
        return $s->customer?->name ?: ('Site #' . $s->customer_id);
    }

    protected function monthLabel(CustomerPeriodSummary $s): string
    {
        return $s->year_month ? '(' . Carbon::parse($s->year_month)->format('M Y') . ')' : '';
    }

    protected function settlementLog(CommissionSettlement $settlement, string $action, ?string $note, ?int $userId, ?string $actorLabel, ?array $meta = null): void
    {
        CommissionSettlementLog::create([
            'commission_settlement_id' => $settlement->id,
            'actor_id' => $userId,
            'actor_label' => $actorLabel ?? (auth()->user()?->name ?? 'System'),
            'action' => $action,
            'note' => $note,
            'meta' => $meta,
        ]);
    }
}
