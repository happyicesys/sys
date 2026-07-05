<?php

namespace App\Services\Refund;

use App\Models\PaymentGatewayLog;
use App\Models\RefundTicket;
use App\Models\RefundTicketItem;
use App\Models\RefundTicketLog;
use App\Models\VendTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefundTicketService
{
    protected RefundMatchingService $matching;
    protected RefundValidationService $validation;

    public function __construct(RefundMatchingService $matching, RefundValidationService $validation)
    {
        $this->matching = $matching;
        $this->validation = $validation;
    }

    /**
     * Create a refund ticket from validated customer input.
     */
    public function create(array $input): RefundTicket
    {
        $vend = $this->matching->resolveMachine($input['machineID']);

        $isManual = (bool) ($input['is_manual'] ?? false);
        $txn = null;
        $log = null;
        $items = [];
        $channel = RefundTicket::CHANNEL_UNKNOWN;
        $cashlessMfg = null;
        $txnRefunded = false;

        if (!$isManual && !empty($input['vend_transaction_id'])) {
            $txn = VendTransaction::withoutGlobalScopes()
                ->with([
                    'vendTransactionItems.product',
                    'vendTransactionItems.vendChannel.product',
                    'vendTransactionItems.vendChannelError',
                    'product',
                    'vendChannel.product',
                    'vendChannelError',
                ])
                ->find($input['vend_transaction_id']);
        }

        if (!$isManual && !$txn && !empty($input['payment_gateway_log_id'])) {
            $log = PaymentGatewayLog::find($input['payment_gateway_log_id']);
        }

        // --- Ownership guard (public, unauthenticated endpoint) ---
        // The form posts raw transaction / gateway-log IDs. WITHOUT this check a
        // caller could submit the ID of ANY transaction in the system (looked up
        // above with global scopes removed) and mint a ticket against another
        // machine/operator with that transaction's amount. Tie every matched
        // source back to the scanned machine, exactly as the matching service
        // does (txn by vend_id, gateway log by vend_code). A non-manual ticket
        // that names a source must resolve to a known machine first.
        if (!$isManual && ($txn || $log) && !$vend) {
            abort(422, 'This machine could not be recognised.');
        }
        if ($txn && $vend && (int) $txn->vend_id !== (int) $vend->id) {
            abort(422, 'The selected transaction does not belong to this machine.');
        }
        if ($log && (string) $log->vend_code !== (string) $input['machineID']) {
            abort(422, 'The selected charge does not belong to this machine.');
        }
        // A source ID was supplied but didn't resolve to a real row — reject
        // rather than silently dropping to a zero/empty ticket.
        if (!$isManual && !empty($input['vend_transaction_id']) && !$txn) {
            abort(422, 'The selected transaction could not be found.');
        }
        if (!$isManual && empty($input['vend_transaction_id']) && !empty($input['payment_gateway_log_id']) && !$log) {
            abort(422, 'The selected charge could not be found.');
        }

        // --- Duplicate-submission guard (matched claims only) ---------------
        // One live claim per matched source. Stops a customer double-tapping
        // Submit (two near-simultaneous POSTs) or re-scanning the same
        // transaction while an earlier claim is still in play. A cache lock
        // keyed by the source SERIALISES truly concurrent requests so the
        // existence check below cannot be raced; the lock is released at the
        // end of create() (finally) or auto-expires via its TTL. Rejected
        // claims don't count, so a genuine resubmit after a rejection is still
        // allowed. Manual claims have no source yet, so they're exempt.
        $submitLock = null;
        if (!$isManual && ($txn || $log)) {
            $sourceKey = $txn ? ('vt-' . $txn->id) : ('pgl-' . $log->id);
            $submitLock = Cache::lock('refund:submit:' . $sourceKey, 15);
            // Wait up to 5s for a concurrent submit of the SAME source to finish.
            // If the wait times out, proceed without the lock — the existence
            // check below is still a strong guard (a slower duplicate lands after
            // the first commits) and we never want to 500 a real customer.
            try {
                $submitLock->block(5);
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                $submitLock = null;
            }

            $liveClaimExists = RefundTicket::where('status', '!=', RefundTicket::STATUS_REJECTED)
                ->when($txn, fn ($q) => $q->where('vend_transaction_id', $txn->id))
                ->when(! $txn && $log, fn ($q) => $q->where('payment_gateway_log_id', $log->id))
                ->exists();

            if ($liveClaimExists) {
                optional($submitLock)->release();
                abort(422, 'A refund request for this transaction has already been submitted. Please check your email for your reference number — our team is reviewing it.');
            }
        }

        if ($txn) {
            $cashlessMfg = $txn->cashless_mfg;
            $channel = $this->matching->classifyChannel($txn->cashless_mfg, $txn->payment_gateway_log_id);
            $txnRefunded = (bool) $txn->is_refunded;
            $items = $this->normalizeTransactionItems($txn, $input['selected_item_ids'] ?? []);
        } elseif ($log) {
            $channel = RefundTicket::CHANNEL_QR;
            $txnRefunded = ((int) $log->status === PaymentGatewayLog::STATUS_REFUND);
            $items = [[
                'vend_transaction_item_id' => null,
                'product_id' => null,
                'product_name' => 'Purchase',
                'vend_channel_code' => null,
                'unit_price_cents' => (int) $log->amount,
                'had_channel_error' => isset($log->is_dispensed) ? ($log->is_dispensed === false) : false,
                'vend_channel_error_code' => null,
                'channel_error_weightage' => null,
                'is_refunded' => $txnRefunded,
            ]];
        }

        $isAutoChannel = $this->matching->isAutoRefundTerminal($cashlessMfg);

        $validation = $this->validation->validate($items, [
            'is_auto_refund_channel' => $isAutoChannel,
            'txn_already_refunded' => $txnRefunded,
            'is_manual' => $isManual,
        ]);

        // Refund amount = only the items still owed (exclude any already-refunded items).
        $claimedCents = array_sum(array_map(
            fn ($i) => ($i['already_refunded'] ?? false) ? 0 : (int) ($i['unit_price_cents'] ?? 0),
            $validation['items']
        ));

        $autoDetected = (bool) $validation['auto_refund_detected'];
        // Only fully auto-resolve when there is genuinely nothing left to pay:
        //  - Nayax: processor refunds externally, or
        //  - every selected item is already refunded (recommendation == reject).
        $allAlreadyRefunded = $validation['recommendation'] === RefundTicket::REC_REJECT;

        if ($isAutoChannel) {
            $status = RefundTicket::STATUS_AUTO_RESOLVED;
            $method = RefundTicket::METHOD_NAYAX_AUTO;
        } elseif ($allAlreadyRefunded) {
            $status = RefundTicket::STATUS_AUTO_RESOLVED;
            $method = $input['refund_method'] ?? RefundTicket::METHOD_NONE;
        } else {
            // a partial already-refunded ticket still has owed items -> normal flow
            $status = RefundTicket::STATUS_SUBMITTED;
            $method = $input['refund_method'] ?? RefundTicket::METHOD_PAYNOW;
        }

        try {
        return DB::transaction(function () use ($input, $vend, $txn, $log, $isManual, $channel, $isAutoChannel, $validation, $claimedCents, $status, $method, $autoDetected) {
            $ticket = RefundTicket::create([
                'reference' => 'PENDING',
                'vend_code' => $input['machineID'],
                'vend_id' => $vend?->id,
                'operator_id' => $vend?->operator_id,
                'vend_transaction_id' => $txn?->id,
                'payment_gateway_log_id' => $txn?->payment_gateway_log_id ?? $log?->id,
                'order_id' => $txn?->order_id ?? $log?->order_id,
                'reason_code' => $input['reason_code'] ?? null,
                'reason_text' => $input['reason_text'] ?? null,
                'manual_items_summary' => $input['manual_items_summary'] ?? null,
                'manual_pay_method' => $input['manual_pay_method'] ?? null,
                'refund_method' => $method,
                'payout_destination' => $isAutoChannel ? null : ($input['payout_destination'] ?? null),
                'contact_name' => $input['contact_name'] ?? null,
                'contact_email' => $input['contact_email'] ?? null,
                'contact_phone' => $input['contact_phone'] ?? null,
                'claimed_amount_cents' => $claimedCents,
                'is_manual' => $isManual,
                'entered_day' => $input['entered_day'] ?? null,
                'entered_amount_cents' => $input['entered_amount_cents'] ?? null,
                'approx_time' => $input['approx_time'] ?? null,
                'payment_channel' => $channel,
                'is_auto_refund_channel' => $isAutoChannel,
                'system_recommendation' => $validation['recommendation'],
                'system_validation_json' => $validation['evidence'],
                'auto_refund_detected' => $autoDetected,
                'status' => $status,
                'submit_ip' => $input['submit_ip'] ?? null,
            ]);

            // Reference = PREFIX-yymmdd + a per-day running number, e.g. RF-260703001.
            // The daily counter is derived by counting today's tickets up to this
            // one; lockForUpdate serialises concurrent submissions so the unique
            // reference can't collide.
            $createdAt = $ticket->created_at ?? now();
            $prefix = config('refund.reference_prefix', 'RF');
            $seq = RefundTicket::whereDate('created_at', $createdAt->toDateString())
                ->where('id', '<=', $ticket->id)
                ->lockForUpdate()
                ->count();
            $ticket->reference = $prefix . '-' . $createdAt->format('ymd') . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $ticket->save();

            foreach ($validation['items'] as $i) {
                RefundTicketItem::create([
                    'refund_ticket_id' => $ticket->id,
                    'vend_transaction_item_id' => $i['vend_transaction_item_id'] ?? null,
                    'product_id' => $i['product_id'] ?? null,
                    'product_name' => $i['product_name'] ?? null,
                    'product_sku' => $i['product_sku'] ?? null,
                    'vend_channel_code' => $i['vend_channel_code'] ?? null,
                    'unit_price_cents' => (int) ($i['unit_price_cents'] ?? 0),
                    'had_channel_error' => (bool) ($i['had_channel_error'] ?? false),
                    'vend_channel_error_code' => $i['vend_channel_error_code'] ?? null,
                    'channel_error_desc' => $i['channel_error_desc'] ?? null,
                    'channel_error_weightage' => $i['channel_error_weightage'] ?? null,
                    'item_recommendation' => $i['item_recommendation'] ?? null,
                    'approved' => null,
                ]);
            }

            $this->log($ticket, 'submitted', null, $ticket->status, 'Customer submitted refund ticket', 'Customer');
            $this->log($ticket, 'validated', null, null, 'System recommendation: ' . $validation['recommendation'], 'System');

            return $ticket->fresh(['items']);
        });
        } finally {
            optional($submitLock)->release();
        }
    }

    /**
     * Ops manually attaches a source transaction (by Order ID) to a ticket that
     * was submitted without a match (manual path). Rebuilds the flagged items
     * and re-runs the auto-validation against the real transaction, so the
     * ticket afterwards carries the same detail as an auto-matched one
     * (transaction date / paid amount / pay method show up on Index + Show).
     *
     * @throws \RuntimeException on business-rule rejections (already matched,
     *                           terminal status, order not found, double refund)
     */
    public function matchOrder(RefundTicket $ticket, string $orderId, ?int $userId = null, ?string $actorLabel = null): RefundTicket
    {
        // An already-matched ticket CAN be re-matched (to fix a wrong Order ID) at
        // any stage of the workflow — the double-refund guard below excludes this
        // ticket itself. Only truly terminal / auto-resolved tickets are blocked.
        // If the ticket was payout-locked (approved/scheduled), re-matching
        // re-derives the amount, so it is dropped back to Submitted for re-verify
        // (handled after save) — the approved figure never changes silently.
        if (in_array($ticket->status, [
            RefundTicket::STATUS_REJECTED,
            RefundTicket::STATUS_COMPLETED,
            RefundTicket::STATUS_AUTO_RESOLVED,
        ], true)) {
            throw new \RuntimeException('Cannot match a ticket that is already ' . $ticket->status . '.');
        }

        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new \RuntimeException('Please enter an Order ID.');
        }

        // Look up the transaction the same way the auto-match does — tied to the
        // ticket's machine so an Order ID from another machine can't be attached.
        $txn = VendTransaction::withoutGlobalScopes()
            ->with([
                'vendTransactionItems.product',
                'vendTransactionItems.vendChannel.product',
                'vendTransactionItems.vendChannelError',
                'product',
                'vendChannel.product',
                'vendChannelError',
            ])
            ->where('order_id', $orderId)
            ->when($ticket->vend_id, fn ($q) => $q->where('vend_id', $ticket->vend_id))
            ->first();

        $log = null;
        if (!$txn) {
            $log = PaymentGatewayLog::query()
                ->where('order_id', $orderId)
                ->when($ticket->vend_code, fn ($q) => $q->where('vend_code', $ticket->vend_code))
                ->first();
        }
        if (!$txn && !$log) {
            // Most obvious admin error: the Order ID DOES exist, just on a
            // different machine. Stop right here with an explicit message rather
            // than a vague "not found", so a wrong Order ID is caught immediately.
            $otherTxn = VendTransaction::withoutGlobalScopes()->where('order_id', $orderId)->first();
            $otherLog = $otherTxn ? null : PaymentGatewayLog::query()->where('order_id', $orderId)->first();
            if ($otherTxn || $otherLog) {
                $otherMachine = $otherTxn
                    ? (optional(\App\Models\Vend::withoutGlobalScopes()->find($otherTxn->vend_id))->code ?? $otherTxn->vend_id)
                    : $otherLog->vend_code;
                throw new \RuntimeException("Order ID {$orderId} belongs to machine {$otherMachine}, not this claim's machine {$ticket->vend_code}. Please double-check the Order ID.");
            }
            throw new \RuntimeException("No transaction with Order ID '{$orderId}' found for machine {$ticket->vend_code}.");
        }

        // Rebuild items + validation exactly like create() does for a matched source.
        $items = [];
        $channel = RefundTicket::CHANNEL_UNKNOWN;
        $cashlessMfg = null;
        $txnRefunded = false;

        if ($txn) {
            $cashlessMfg = $txn->cashless_mfg;
            $channel = $this->matching->classifyChannel($txn->cashless_mfg, $txn->payment_gateway_log_id);
            $txnRefunded = (bool) $txn->is_refunded;
            $items = $this->normalizeTransactionItems($txn, []);
        } else {
            $channel = RefundTicket::CHANNEL_QR;
            $txnRefunded = ((int) $log->status === PaymentGatewayLog::STATUS_REFUND);
            $items = [[
                'vend_transaction_item_id' => null,
                'product_id' => null,
                'product_name' => 'Purchase',
                'vend_channel_code' => null,
                'unit_price_cents' => (int) $log->amount,
                'had_channel_error' => isset($log->is_dispensed) ? ($log->is_dispensed === false) : false,
                'vend_channel_error_code' => null,
                'channel_error_weightage' => null,
                'is_refunded' => $txnRefunded,
            ]];
        }

        $isAutoChannel = $this->matching->isAutoRefundTerminal($cashlessMfg);
        $validation = $this->validation->validate($items, [
            'is_auto_refund_channel' => $isAutoChannel,
            'txn_already_refunded' => $txnRefunded,
            'is_manual' => true, // it remains a manually-submitted claim
        ]);
        $claimedCents = array_sum(array_map(
            fn ($i) => ($i['already_refunded'] ?? false) ? 0 : (int) ($i['unit_price_cents'] ?? 0),
            $validation['items']
        ));

        return DB::transaction(function () use ($ticket, $txn, $log, $orderId, $channel, $isAutoChannel, $validation, $claimedCents, $userId, $actorLabel) {
            $from = $ticket->status;

            // Backfill machine/operator if the vend was unrecognised at submission.
            $vendId = $ticket->vend_id ?? $txn?->vend_id ?? $log?->vend_id;
            $operatorId = $ticket->operator_id;
            if (!$operatorId && $vendId) {
                $operatorId = optional(\App\Models\Vend::withoutGlobalScopes()->find($vendId))->operator_id;
            }

            // Set the identity fields first so the standard double-refund guard applies.
            $ticket->vend_transaction_id = $txn?->id;
            $ticket->payment_gateway_log_id = $txn?->payment_gateway_log_id ?? $log?->id;
            $ticket->order_id = $txn?->order_id ?? $log?->order_id;
            if ($conflict = $ticket->conflictingRefund()) {
                throw new \RuntimeException("This transaction is already being refunded under {$conflict->reference}.");
            }

            $ticket->fill([
                'vend_id' => $vendId,
                'operator_id' => $operatorId,
                'payment_channel' => $channel,
                'is_auto_refund_channel' => $isAutoChannel,
                'system_recommendation' => $validation['recommendation'],
                'system_validation_json' => $validation['evidence'],
                'auto_refund_detected' => (bool) $validation['auto_refund_detected'],
                'claimed_amount_cents' => $claimedCents,
            ]);

            // Nothing left to pay (Nayax auto-refund / everything already refunded)
            // -> resolve the ticket, mirroring create().
            if ($isAutoChannel) {
                $ticket->status = RefundTicket::STATUS_AUTO_RESOLVED;
                $ticket->refund_method = RefundTicket::METHOD_NAYAX_AUTO;
            } elseif ($validation['recommendation'] === RefundTicket::REC_REJECT) {
                $ticket->status = RefundTicket::STATUS_AUTO_RESOLVED;
            } elseif (in_array($from, [RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED], true)) {
                // Re-matching changed the amount on a payout-locked ticket — send it
                // back to Submitted so it must be re-verified (the single verify gate
                // now also approves) and the locked-in figure is never stale.
                $ticket->status = RefundTicket::STATUS_SUBMITTED;
            }
            $ticket->save();

            // Replace the (empty/manual) items with the real transaction items.
            $ticket->items()->delete();
            foreach ($validation['items'] as $i) {
                RefundTicketItem::create([
                    'refund_ticket_id' => $ticket->id,
                    'vend_transaction_item_id' => $i['vend_transaction_item_id'] ?? null,
                    'product_id' => $i['product_id'] ?? null,
                    'product_name' => $i['product_name'] ?? null,
                    'product_sku' => $i['product_sku'] ?? null,
                    'vend_channel_code' => $i['vend_channel_code'] ?? null,
                    'unit_price_cents' => (int) ($i['unit_price_cents'] ?? 0),
                    'had_channel_error' => (bool) ($i['had_channel_error'] ?? false),
                    'vend_channel_error_code' => $i['vend_channel_error_code'] ?? null,
                    'channel_error_desc' => $i['channel_error_desc'] ?? null,
                    'channel_error_weightage' => $i['channel_error_weightage'] ?? null,
                    'item_recommendation' => $i['item_recommendation'] ?? null,
                    'approved' => null,
                ]);
            }

            $this->log($ticket, 'matched', $from, $ticket->status, "Ops matched Order ID {$orderId}", $actorLabel ?? 'Ops', $userId);
            $this->log($ticket, 'validated', null, null, 'System recommendation: ' . $validation['recommendation'], 'System');

            return $ticket->fresh(['items']);
        });
    }

    /**
     * Unlink a wrongly-matched transaction and return the ticket to an unmatched
     * manual state (so Ops can re-enter the correct Order ID). Re-runs validation
     * with no items. Blocked for payout-locked / terminal tickets.
     *
     * @throws \RuntimeException
     */
    public function clearMatch(RefundTicket $ticket, ?int $userId = null, ?string $actorLabel = null): RefundTicket
    {
        if (! $ticket->vend_transaction_id && ! $ticket->payment_gateway_log_id) {
            throw new \RuntimeException('This ticket is not matched to any transaction.');
        }
        // Clearing resets the ticket to an unmatched Submitted claim (below), which
        // naturally unlocks any payout state — so only terminal / auto-resolved
        // tickets are blocked.
        if (in_array($ticket->status, [
            RefundTicket::STATUS_REJECTED,
            RefundTicket::STATUS_COMPLETED,
            RefundTicket::STATUS_AUTO_RESOLVED,
        ], true)) {
            throw new \RuntimeException('Cannot clear the match on a ticket that is already ' . $ticket->status . '.');
        }

        $validation = $this->validation->validate([], [
            'is_auto_refund_channel' => false,
            'txn_already_refunded' => false,
            'is_manual' => true,
        ]);

        return DB::transaction(function () use ($ticket, $validation, $userId, $actorLabel) {
            $from = $ticket->status;
            $priorOrderId = $ticket->order_id;

            $ticket->items()->delete();
            $ticket->fill([
                'vend_transaction_id' => null,
                'payment_gateway_log_id' => null,
                'order_id' => null,
                'is_manual' => true,
                'payment_channel' => RefundTicket::CHANNEL_UNKNOWN,
                'is_auto_refund_channel' => false,
                'auto_refund_detected' => false,
                'system_recommendation' => $validation['recommendation'],
                'system_validation_json' => $validation['evidence'],
                'claimed_amount_cents' => (int) ($ticket->entered_amount_cents ?? 0),
                'status' => RefundTicket::STATUS_SUBMITTED,
            ]);
            $ticket->save();

            $this->log($ticket, 'unmatched', $from, $ticket->status, "Ops cleared the match (was Order ID {$priorOrderId})", $actorLabel ?? 'Ops', $userId);

            return $ticket->fresh(['items']);
        });
    }

    /**
     * @return array<int, array>
     */
    protected function normalizeTransactionItems(VendTransaction $txn, array $selectedIds): array
    {
        $rows = $txn->vendTransactionItems;

        // No line-item rows recorded — treat the whole transaction as one implicit item.
        if ($rows->isEmpty()) {
            $product = $txn->product ?? $txn->vendChannel?->product;
            $headerHasError = (bool) $txn->vend_channel_error_id && $this->matching->isRealChannelError($txn->vendChannelError?->code);
            $fallback = $headerHasError ? null : $this->matching->fallbackChannelError($txn, $txn->vend_channel_code);
            return [[
                'vend_transaction_item_id' => null,
                'product_id' => $txn->product_id,
                'product_name' => $product?->name ?? ($txn->vend_channel_code ? 'Channel ' . $txn->vend_channel_code : 'Purchase'),
                'product_sku' => $product?->code ?? $txn->vendChannel?->sku_code ?? $txn->vend_channel_code,
                'vend_channel_code' => $txn->vend_channel_code,
                'unit_price_cents' => (int) $txn->amount,
                'had_channel_error' => $headerHasError || $fallback !== null,
                'vend_channel_error_code' => $txn->vendChannelError?->code ?? ($fallback['code'] ?? null),
                'channel_error_desc' => $txn->vendChannelError?->desc ?? ($fallback['desc'] ?? null),
                'channel_error_weightage' => $txn->vendChannelError?->weightage ?? ($fallback['weightage'] ?? null),
                'is_refunded' => (bool) $txn->is_refunded,
            ]];
        }

        $selectedIds = array_map('intval', $selectedIds);

        // Single-item purchase, or nothing selected -> take all items.
        $selected = $rows;
        if ($rows->count() > 1 && !empty($selectedIds)) {
            $selected = $rows->whereIn('id', $selectedIds);
            if ($selected->isEmpty()) {
                $selected = $rows; // defensive: never end up with zero items
            }
        }

        return $selected->map(function ($item) use ($txn) {
            $error = $item->vendChannelError;
            $product = $item->product ?? $item->vendChannel?->product;
            $itemHasError = (bool) $item->vend_channel_error_id && $this->matching->isRealChannelError($item->vend_channel_error_code);
            // Fall back to items_json / header when the pre-created item row never
            // got its channel error backfilled (see fallbackChannelError()).
            $fallback = $itemHasError ? null : $this->matching->fallbackChannelError($txn, $item->vend_channel_code);
            return [
                'vend_transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? ($item->product_name ?? ($item->vend_channel_code ? 'Channel ' . $item->vend_channel_code : 'Item')),
                'product_sku' => $product?->code ?? $item->vendChannel?->sku_code ?? $item->vend_channel_code,
                'vend_channel_code' => $item->vend_channel_code,
                'unit_price_cents' => (int) ($item->unit_price_amount ?: ($item->vendChannel?->amount ?? 0)),
                'had_channel_error' => $itemHasError || $fallback !== null,
                'vend_channel_error_code' => $item->vend_channel_error_code ?? ($fallback['code'] ?? null),
                'channel_error_desc' => $error?->desc ?? ($fallback['desc'] ?? null),
                'channel_error_weightage' => $error?->weightage ?? ($fallback['weightage'] ?? null),
                'is_refunded' => (bool) $item->is_refunded,
            ];
        })->values()->all();
    }

    /**
     * The system auto-refunded the underlying charge AFTER a customer had already
     * raised a refund ticket for it (e.g. RefundOmiseJob refunds an Omise sale
     * that didn't dispense). Resolve every live ticket for that charge and email
     * the customer that it was handled automatically — no manual PayNow / PayPal
     * payout is needed. This also stops a second (manual) payout going out on top
     * of the processor refund.
     *
     * Matched by order_id / payment_gateway_log_id / vend_transaction_id so it
     * works whether the ticket linked a sales transaction or a bare gateway log.
     * Terminal + already-auto-resolved tickets are skipped (idempotent), so
     * re-running the payment job never double-emails.
     *
     * Best-effort by contract: callers (payment jobs) MUST wrap this so a
     * ticket/email hiccup can never break the actual payment refund.
     *
     * @return int number of tickets resolved
     */
    public function markAutoRefundedByCharge(?string $orderId, ?int $paymentGatewayLogId = null, ?int $vendTransactionId = null): int
    {
        if (!$orderId && !$paymentGatewayLogId && !$vendTransactionId) {
            return 0;
        }

        $tickets = RefundTicket::query()
            ->whereNotIn('status', [
                RefundTicket::STATUS_REJECTED,
                RefundTicket::STATUS_COMPLETED,
                RefundTicket::STATUS_AUTO_RESOLVED,
            ])
            ->where(function ($q) use ($orderId, $paymentGatewayLogId, $vendTransactionId) {
                if ($orderId) {
                    $q->orWhere('order_id', $orderId);
                }
                if ($paymentGatewayLogId) {
                    $q->orWhere('payment_gateway_log_id', $paymentGatewayLogId);
                }
                if ($vendTransactionId) {
                    $q->orWhere('vend_transaction_id', $vendTransactionId);
                }
            })
            ->get();

        $resolved = 0;
        foreach ($tickets as $ticket) {
            $from = $ticket->status;
            $ticket->update([
                'status' => RefundTicket::STATUS_AUTO_RESOLVED,
                'auto_refund_detected' => true,
            ]);
            $this->log(
                $ticket,
                'auto_resolved',
                $from,
                $ticket->status,
                'System auto-refunded the charge; ticket resolved automatically. Email the customer via the "No charge / auto-refund" action when ready.',
                'System'
            );
            // NOTE: the "auto-refund already processed" email is intentionally NOT
            // sent here anymore. Per the requested workflow, no customer email fires
            // automatically off a vend_transactions/charge refund — the admin sends
            // it deliberately from the ticket's "No charge / auto-refund" action.
            $resolved++;
        }

        return $resolved;
    }

    public function log(RefundTicket $ticket, string $action, ?string $from, ?string $to, ?string $note = null, string $actorLabel = 'System', ?int $actorId = null): void
    {
        RefundTicketLog::create([
            'refund_ticket_id' => $ticket->id,
            'actor_id' => $actorId,
            'actor_label' => $actorLabel,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
