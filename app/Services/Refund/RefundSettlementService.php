<?php

namespace App\Services\Refund;

use App\Models\Operator;
use App\Models\PayoutGroup;
use App\Models\RefundPayoutBatch;
use App\Models\RefundSettlementExport;
use App\Models\RefundSettlementLog;
use App\Models\RefundTicket;
use App\Services\Refund\BankTemplates\BankTemplateRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Refund Settlement — batches approved refund tickets into dated, per-payout-group
 * pools between approval and payout, so the CIMB / PayPal file is exported on a
 * controlled boundary (not the instant a ticket is approved). See
 * REFUND_SETTLEMENT_PLAN.md.
 *
 * Lifecycle: open -> closed -> exported -> done. A settlement holds two streams —
 * PayNow (CIMB .txt) and PayPal (.xlsx) — each exported and marked-done separately.
 */
class RefundSettlementService
{
    protected RefundTicketService $tickets;
    protected RefundEmailService $email;

    public function __construct(RefundTicketService $tickets, RefundEmailService $email)
    {
        $this->tickets = $tickets;
        $this->email = $email;
    }

    /**
     * Push selected APPROVED PayNow tickets into the day's OPEN settlement for each
     * ticket's payout head (find-or-create). PayPal tickets are excluded (paid
     * manually, marked done on the Refund Requests page). Tickets already in a
     * settlement, or not approved, are ignored.
     *
     * @return array{pushed:int, settlements:array<string>}
     */
    public function push(array $ticketIds, ?int $userId = null, ?string $actorLabel = 'Admin'): array
    {
        return DB::transaction(function () use ($ticketIds, $userId, $actorLabel) {
            // PayNow only — PayPal refunds are paid manually and marked done from
            // the Refund Requests page, they never enter a settlement.
            $tickets = RefundTicket::whereIn('id', $ticketIds)
                ->where('status', RefundTicket::STATUS_APPROVED)
                ->where('refund_method', RefundTicket::METHOD_PAYNOW)
                ->whereNull('payout_batch_id')
                ->get();

            if ($tickets->isEmpty()) {
                throw new \RuntimeException('No eligible tickets to push. They must be Approved PayNow tickets not already in a settlement (PayPal is marked done on the Refund Requests page).');
            }

            $pushed = 0;
            $refs = [];

            foreach ($tickets->groupBy('operator_id') as $operatorId => $group) {
                if (!$operatorId) {
                    // Without an operator we cannot resolve a payout account — skip.
                    continue;
                }
                $head = $this->headFor((int) $operatorId);
                $settlement = $this->openSettlementFor($head, $userId, $actorLabel);

                foreach ($group as $ticket) {
                    // Defensive double-refund re-check at push time.
                    if ($ticket->conflictingRefund()) {
                        continue;
                    }
                    $from = $ticket->status;
                    $ticket->update([
                        'payout_batch_id' => $settlement->id,
                        'status' => RefundTicket::STATUS_SCHEDULED,
                        'scheduled_at' => now(),
                    ]);
                    $this->tickets->log($ticket, 'scheduled', $from, RefundTicket::STATUS_SCHEDULED, 'Pushed to settlement ' . $settlement->reference, $actorLabel ?? 'Admin', $userId);
                    $this->settlementLog($settlement, 'entry_added', 'Added ' . $ticket->reference . ' (' . $ticket->refund_method . ')', $userId, $actorLabel, ['ticket_id' => $ticket->id, 'reference' => $ticket->reference]);
                    $pushed++;
                    $refs[$settlement->reference] = true;
                }
                $this->recount($settlement);
            }

            if ($pushed === 0) {
                throw new \RuntimeException('Selected tickets could not be pushed (missing operator, or already being refunded elsewhere).');
            }

            return ['pushed' => $pushed, 'settlements' => array_keys($refs)];
        });
    }

    public function close(RefundPayoutBatch $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): void
    {
        $this->assertSettlement($settlement);
        if ($settlement->status !== RefundPayoutBatch::STATUS_OPEN) {
            throw new \RuntimeException('Only an open settlement can be closed.');
        }
        $this->recount($settlement);
        if ((int) $settlement->fresh()->count === 0) {
            throw new \RuntimeException('Cannot close an empty settlement.');
        }
        $settlement->update([
            'status' => RefundPayoutBatch::STATUS_CLOSED,
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);
        $this->settlementLog($settlement, 'closed', 'Settlement closed', $userId, $actorLabel);
    }

    /** Undo a close: a Closed settlement goes back to Open so tickets can be added/removed again. */
    public function reopen(RefundPayoutBatch $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): void
    {
        $this->assertSettlement($settlement);
        if ($settlement->status === RefundPayoutBatch::STATUS_OPEN) {
            throw new \RuntimeException('This settlement is already open.');
        }
        $settlement->update([
            'status' => RefundPayoutBatch::STATUS_OPEN,
            'closed_by' => null,
            'closed_at' => null,
        ]);
        $this->settlementLog($settlement, 'reopened', 'Settlement reopened', $userId, $actorLabel);
    }

    /**
     * PayNow stream -> CIMB %-delimited .txt. Blocks (does not silently fall back to
     * the global config account) when the head has no originating account.
     *
     * @return array{filename:string, content:string, path:string}
     */
    public function exportCimb(RefundPayoutBatch $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): array
    {
        $this->assertSettlement($settlement);

        return DB::transaction(function () use ($settlement, $userId, $actorLabel) {
            // Export the whole PayNow batch — every member, whatever its stage. This
            // lets the file be regenerated for the record even after rows have been
            // marked done or flagged insufficient info (it no longer blocks once
            // nothing is left in `scheduled`). Only a settlement with zero PayNow
            // members has genuinely nothing to export.
            $tickets = $this->members($settlement, RefundTicket::METHOD_PAYNOW, [
                RefundTicket::STATUS_SCHEDULED,
                RefundTicket::STATUS_COMPLETED,
                RefundTicket::STATUS_INSUFFICIENT_INFO,
            ]);
            if ($tickets->isEmpty()) {
                throw new \RuntimeException('No PayNow tickets to export in this settlement.');
            }

            $account = $this->resolveOriginatingAccount($settlement);
            $template = BankTemplateRegistry::make('cimb');
            $content = $template->generate($tickets, ['originating_account' => $account, 'batch' => $settlement]);

            $filename = $settlement->reference . '-cimb.' . $template->fileExtension();
            $path = 'refund-payouts/' . $filename;
            Storage::disk('local')->put($path, $content);

            $total = (int) $tickets->sum('payout_amount_cents');
            RefundSettlementExport::create([
                'refund_payout_batch_id' => $settlement->id,
                'method' => RefundTicket::METHOD_PAYNOW,
                'format' => RefundSettlementExport::FORMAT_CIMB_TXT,
                'file_path' => $path,
                'count' => $tickets->count(),
                'total_cents' => $total,
                'exported_by' => $userId,
                'exported_at' => now(),
            ]);

            // Export does NOT change the open/closed status — it just records the file.
            $settlement->update([
                'exported_by' => $userId,
                'exported_at' => now(),
                'csv_path' => $path,
            ]);
            $this->settlementLog($settlement, 'exported_cimb', 'Exported CIMB file ' . $filename . ' (' . $tickets->count() . ' PayNow, $' . number_format($total / 100, 2) . ')', $userId, $actorLabel, ['file' => $path, 'count' => $tickets->count()]);

            return ['filename' => $filename, 'content' => $content, 'path' => $path];
        });
    }

    /**
     * PayPal stream -> .xlsx worklist (email + amount) for the admin to pay manually.
     *
     * @return array{filename:string, path:string}
     */
    public function exportXlsx(RefundPayoutBatch $settlement, ?int $userId = null, ?string $actorLabel = 'Admin'): array
    {
        $this->assertSettlement($settlement);

        $tickets = $this->members($settlement, RefundTicket::METHOD_PAYPAL, [RefundTicket::STATUS_SCHEDULED]);
        if ($tickets->isEmpty()) {
            throw new \RuntimeException('No PayPal tickets to export in this settlement.');
        }

        $rows = $tickets->map(fn (RefundTicket $t) => [
            'Reference' => $t->reference,
            'PayPal Email' => $t->payout_destination,
            'Amount (SGD)' => number_format($t->payout_amount_cents / 100, 2, '.', ''),
            'Operator' => optional(Operator::withoutGlobalScopes()->find($t->operator_id))->name,
            'Machine' => $t->vend_code,
            'Contact Email' => $t->contact_email,
            'Submitted' => optional($t->created_at)->toDateString(),
        ])->values();

        $filename = $settlement->reference . '-paypal.xlsx';
        $rel = 'refund-payouts/' . $filename;
        Storage::disk('local')->makeDirectory('refund-payouts');
        $abs = Storage::disk('local')->path($rel);
        (new FastExcel(collect($rows)))->export($abs);

        return DB::transaction(function () use ($settlement, $tickets, $rel, $filename, $userId, $actorLabel) {
            $total = (int) $tickets->sum('payout_amount_cents');
            RefundSettlementExport::create([
                'refund_payout_batch_id' => $settlement->id,
                'method' => RefundTicket::METHOD_PAYPAL,
                'format' => RefundSettlementExport::FORMAT_XLSX,
                'file_path' => $rel,
                'count' => $tickets->count(),
                'total_cents' => $total,
                'exported_by' => $userId,
                'exported_at' => now(),
            ]);
            $settlement->update(['exported_by' => $userId, 'exported_at' => now()]);
            $this->settlementLog($settlement, 'exported_xlsx', 'Exported PayPal worklist ' . $filename . ' (' . $tickets->count() . ')', $userId, $actorLabel, ['file' => $rel, 'count' => $tickets->count()]);

            return ['filename' => $filename, 'path' => $rel];
        });
    }

    /**
     * Mark the selected member tickets done (the ones the bank/PayPal actually
     * paid). Only the checked rows are completed; the rest stay pending in the
     * settlement for fix / return-to-pool. When every member is completed the
     * settlement flips to DONE.
     */
    public function markDone(RefundPayoutBatch $settlement, array $ticketIds, ?int $userId = null, ?string $actorLabel = 'Admin'): int
    {
        $this->assertSettlement($settlement);
        // Marking a refund done is allowed whether the settlement is open or closed:
        // the admin may pay a row via PayNow/PayPal and tick it done before formally
        // closing the batch. Membership + not-already-completed are still enforced below.

        $completed = DB::transaction(function () use ($settlement, $ticketIds, $userId, $actorLabel) {
            $tickets = RefundTicket::whereIn('id', $ticketIds)
                ->where('payout_batch_id', $settlement->id)
                // Insufficient-info rows (bank couldn't pay) can also be marked done
                // here once the admin has handled the payout by hand.
                ->whereIn('status', [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_INSUFFICIENT_INFO])
                ->get();

            if ($tickets->isEmpty()) {
                throw new \RuntimeException('None of the selected tickets can be marked done (must be in this settlement and not already completed).');
            }

            foreach ($tickets as $ticket) {
                $from = $ticket->status;
                $ticket->update([
                    'status' => RefundTicket::STATUS_COMPLETED,
                    'paid_at' => $ticket->paid_at ?? now(),
                    'completed_at' => now(),
                ]);
                $this->tickets->log($ticket, 'completed', $from, $ticket->status, 'Refund done via settlement ' . $settlement->reference, $actorLabel ?? 'Admin', $userId);
                $this->settlementLog($settlement, 'marked_done', 'Marked ' . $ticket->reference . ' done', $userId, $actorLabel, ['ticket_id' => $ticket->id]);
            }

            $this->recount($settlement);

            // No manual close: auto-close once every member refund is completed.
            $remaining = RefundTicket::where('payout_batch_id', $settlement->id)
                ->where('status', '!=', RefundTicket::STATUS_COMPLETED)
                ->count();
            if ($remaining === 0 && $settlement->status === RefundPayoutBatch::STATUS_OPEN) {
                $settlement->update([
                    'status' => RefundPayoutBatch::STATUS_CLOSED,
                    'closed_by' => $userId,
                    'closed_at' => now(),
                ]);
                $this->settlementLog($settlement, 'closed', 'Auto-closed — all refunds completed', $userId, $actorLabel);
            }

            return $tickets;
        });

        // Completion emails are queued only AFTER the transaction commits, so a mail
        // hiccup can never roll back the completions, and no "refund done" email
        // ever goes out for a completion that was rolled back. Delivery itself is
        // gated by REFUND_EMAIL_ENABLED and internally guarded against throwing.
        foreach ($completed as $ticket) {
            $this->email->queue($ticket, RefundEmailService::T_COMPLETED);
        }

        return $completed->count();
    }

    /**
     * Flag the selected member tickets as "insufficient info" — the bank could not
     * pay them (bad / missing PayNow number, etc.). They STAY in the settlement for
     * the record but drop out of the CIMB export and mark-done flow; an admin then
     * handles them by hand and batch-completes them from the Refund Requests page.
     * Only scheduled/approved (not-yet-completed) members can be flagged.
     */
    public function markInsufficientInfo(RefundPayoutBatch $settlement, array $ticketIds, ?int $userId = null, ?string $actorLabel = 'Admin'): int
    {
        $this->assertSettlement($settlement);

        return DB::transaction(function () use ($settlement, $ticketIds, $userId, $actorLabel) {
            $tickets = RefundTicket::whereIn('id', $ticketIds)
                ->where('payout_batch_id', $settlement->id)
                ->whereIn('status', [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_APPROVED])
                ->get();

            if ($tickets->isEmpty()) {
                throw new \RuntimeException('None of the selected tickets can be flagged (must be in this settlement and not already completed).');
            }

            foreach ($tickets as $ticket) {
                $from = $ticket->status;
                $ticket->update(['status' => RefundTicket::STATUS_INSUFFICIENT_INFO]);
                $this->tickets->log($ticket, 'insufficient_info', $from, $ticket->status, 'Flagged insufficient info (handle manually) in settlement ' . $settlement->reference, $actorLabel ?? 'Admin', $userId);
                $this->settlementLog($settlement, 'insufficient_info', 'Flagged ' . $ticket->reference . ' as insufficient info', $userId, $actorLabel, ['ticket_id' => $ticket->id]);
            }

            // Kept as a member, so the settlement count/total stay unchanged.
            $this->recount($settlement);

            return $tickets->count();
        });
    }

    /**
     * Return a (typically bounced) member ticket to the pool: unlink it and set it
     * back to Approved so it re-appears on /refunds for a fresh push. History is
     * preserved in both logs even though payout_batch_id is cleared.
     */
    public function returnToPool(RefundPayoutBatch $settlement, RefundTicket $ticket, ?int $userId = null, ?string $actorLabel = 'Admin'): void
    {
        $this->assertSettlement($settlement);
        if ($settlement->status !== RefundPayoutBatch::STATUS_OPEN) {
            throw new \RuntimeException('Tickets can only be removed while the settlement is Open (this one is ' . $settlement->status . ').');
        }
        if ((int) $ticket->payout_batch_id !== (int) $settlement->id) {
            throw new \RuntimeException('That ticket is not in this settlement.');
        }
        if ($ticket->status === RefundTicket::STATUS_COMPLETED) {
            throw new \RuntimeException('A completed refund cannot be removed.');
        }

        DB::transaction(function () use ($settlement, $ticket, $userId, $actorLabel) {
            $from = $ticket->status;
            $ticket->update([
                'status' => RefundTicket::STATUS_APPROVED,
                'payout_batch_id' => null,
                'scheduled_at' => null,
            ]);
            $this->tickets->log($ticket, 'returned_to_pool', $from, $ticket->status, 'Returned to pool from settlement ' . $settlement->reference, $actorLabel ?? 'Admin', $userId);
            $this->settlementLog($settlement, 'entry_removed', 'Returned ' . $ticket->reference . ' to pool', $userId, $actorLabel, ['ticket_id' => $ticket->id]);
            $this->recount($settlement);
        });
    }

    /** Delete an OPEN settlement that has no members (e.g. its only ticket got rejected). */
    public function voidEmpty(RefundPayoutBatch $settlement, ?int $userId = null): void
    {
        $this->assertSettlement($settlement);
        if ($settlement->status !== RefundPayoutBatch::STATUS_OPEN) {
            throw new \RuntimeException('Only an open settlement can be voided.');
        }
        $this->recount($settlement);
        if ((int) $settlement->fresh()->count > 0) {
            throw new \RuntimeException('Settlement is not empty — return its tickets to the pool first.');
        }
        $settlement->settlementLogs()->delete();
        $settlement->delete();
    }

    // ---- internals ----

    /**
     * Resolve a ticket's operator to its settlement head: its payout group (shared
     * account) if grouped, else the operator itself.
     *
     * @return array{payout_group_id:?int, operator_id:?int, code:string}
     */
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

    protected function openSettlementFor(array $head, ?int $userId, ?string $actorLabel): RefundPayoutBatch
    {
        $date = now()->toDateString();

        $scope = fn ($q) => $q->settlements()
            ->whereDate('settlement_date', $date)
            ->when($head['payout_group_id'], fn ($w) => $w->where('payout_group_id', $head['payout_group_id']), fn ($w) => $w->whereNull('payout_group_id'))
            ->when($head['operator_id'], fn ($w) => $w->where('operator_id', $head['operator_id']), fn ($w) => $w->whereNull('operator_id'));

        $open = $scope(RefundPayoutBatch::query())
            ->where('status', RefundPayoutBatch::STATUS_OPEN)
            ->lockForUpdate()
            ->first();
        if ($open) {
            return $open;
        }

        $maxSeq = (int) $scope(RefundPayoutBatch::query())->max('sequence');
        $seq = $maxSeq + 1;

        // Build the final RST reference up front (deterministic from date + head
        // code + sequence) and insert with it directly. Avoids the transient
        // 'PENDING' placeholder, which — because reference is UNIQUE — could clash
        // if two settlements were mid-open at the same moment.
        $reference = 'RST-' . now()->format('ymd') . '-' . strtoupper((string) $head['code']) . '-' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        $settlement = RefundPayoutBatch::create([
            'reference' => $reference,
            'is_settlement' => true,
            'settlement_date' => $date,
            'payout_group_id' => $head['payout_group_id'],
            'operator_id' => $head['operator_id'],
            'sequence' => $seq,
            'method' => 'mixed',
            'created_by' => $userId,
            'count' => 0,
            'total_cents' => 0,
            'status' => RefundPayoutBatch::STATUS_OPEN,
        ]);

        $this->settlementLog($settlement, 'created', 'Settlement opened', $userId, $actorLabel);

        return $settlement;
    }

    /** Live member collection for a settlement, optionally filtered by method/status. */
    protected function members(RefundPayoutBatch $settlement, ?string $method = null, ?array $statuses = null)
    {
        return RefundTicket::where('payout_batch_id', $settlement->id)
            ->when($method, fn ($q) => $q->where('refund_method', $method))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->get();
    }

    /** Recompute count/total from current members (scheduled + completed). */
    protected function recount(RefundPayoutBatch $settlement): void
    {
        $agg = RefundTicket::where('payout_batch_id', $settlement->id)
            ->whereIn('status', [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_COMPLETED, RefundTicket::STATUS_INSUFFICIENT_INFO])
            ->selectRaw('count(*) as c, coalesce(sum(coalesce(final_refund_amount_cents, claimed_amount_cents)),0) as t')
            ->first();
        $settlement->update(['count' => (int) $agg->c, 'total_cents' => (int) $agg->t]);
    }

    /** @return array{no:string, name:string} */
    protected function resolveOriginatingAccount(RefundPayoutBatch $settlement): array
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
            throw new \RuntimeException("No originating CIMB account set for {$label}. Set the bank account on its payout group (or the operator) before exporting.");
        }
        return ['no' => $no, 'name' => $name];
    }

    protected function assertSettlement(RefundPayoutBatch $settlement): void
    {
        abort_unless($settlement->is_settlement, 404);
    }

    protected function settlementLog(RefundPayoutBatch $settlement, string $action, ?string $note, ?int $userId, ?string $actorLabel, ?array $meta = null): void
    {
        RefundSettlementLog::create([
            'refund_payout_batch_id' => $settlement->id,
            'actor_id' => $userId,
            'actor_label' => $actorLabel ?? (auth()->user()?->name ?? 'System'),
            'action' => $action,
            'note' => $note,
            'meta' => $meta,
        ]);
    }
}
