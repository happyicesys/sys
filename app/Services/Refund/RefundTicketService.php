<?php

namespace App\Services\Refund;

use App\Models\PaymentGatewayLog;
use App\Models\RefundTicket;
use App\Models\RefundTicketItem;
use App\Models\RefundTicketLog;
use App\Models\VendTransaction;
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
                'refund_method' => $method,
                'payout_destination' => $isAutoChannel ? null : ($input['payout_destination'] ?? null),
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

            $prefix = config('refund.reference_prefix', 'RFD');
            $ticket->reference = $prefix . '-' . str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT);
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
        if ($ticket->vend_transaction_id || $ticket->payment_gateway_log_id) {
            throw new \RuntimeException('This ticket is already matched to a transaction.');
        }
        // Terminal tickets can't be re-opened, and approved/scheduled tickets are
        // payout-locked — matching re-derives the refund amount, which must not
        // change once a manager has approved the payout figure.
        if (in_array($ticket->status, [
            RefundTicket::STATUS_REJECTED,
            RefundTicket::STATUS_COMPLETED,
            RefundTicket::STATUS_AUTO_RESOLVED,
            RefundTicket::STATUS_APPROVED,
            RefundTicket::STATUS_SCHEDULED,
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
     * @return array<int, array>
     */
    protected function normalizeTransactionItems(VendTransaction $txn, array $selectedIds): array
    {
        $rows = $txn->vendTransactionItems;

        // No line-item rows recorded — treat the whole transaction as one implicit item.
        if ($rows->isEmpty()) {
            $product = $txn->product ?? $txn->vendChannel?->product;
            return [[
                'vend_transaction_item_id' => null,
                'product_id' => $txn->product_id,
                'product_name' => $product?->name ?? ($txn->vend_channel_code ? 'Channel ' . $txn->vend_channel_code : 'Purchase'),
                'product_sku' => $product?->code ?? $txn->vendChannel?->sku_code ?? $txn->vend_channel_code,
                'vend_channel_code' => $txn->vend_channel_code,
                'unit_price_cents' => (int) $txn->amount,
                'had_channel_error' => (bool) $txn->vend_channel_error_id && $this->matching->isRealChannelError($txn->vendChannelError?->code),
                'vend_channel_error_code' => $txn->vendChannelError?->code,
                'channel_error_desc' => $txn->vendChannelError?->desc,
                'channel_error_weightage' => $txn->vendChannelError?->weightage,
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

        return $selected->map(function ($item) {
            $error = $item->vendChannelError;
            $product = $item->product ?? $item->vendChannel?->product;
            return [
                'vend_transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? ($item->product_name ?? ($item->vend_channel_code ? 'Channel ' . $item->vend_channel_code : 'Item')),
                'product_sku' => $product?->code ?? $item->vendChannel?->sku_code ?? $item->vend_channel_code,
                'vend_channel_code' => $item->vend_channel_code,
                'unit_price_cents' => (int) ($item->unit_price_amount ?: ($item->vendChannel?->amount ?? 0)),
                'had_channel_error' => (bool) $item->vend_channel_error_id && $this->matching->isRealChannelError($item->vend_channel_error_code),
                'vend_channel_error_code' => $item->vend_channel_error_code,
                'channel_error_desc' => $error?->desc,
                'channel_error_weightage' => $error?->weightage,
                'is_refunded' => (bool) $item->is_refunded,
            ];
        })->values()->all();
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
