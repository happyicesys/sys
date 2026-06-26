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
                ->with(['vendTransactionItems.product', 'vendTransactionItems.vendChannelError'])
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
                    'vend_channel_code' => $i['vend_channel_code'] ?? null,
                    'unit_price_cents' => (int) ($i['unit_price_cents'] ?? 0),
                    'had_channel_error' => (bool) ($i['had_channel_error'] ?? false),
                    'vend_channel_error_code' => $i['vend_channel_error_code'] ?? null,
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
     * @return array<int, array>
     */
    protected function normalizeTransactionItems(VendTransaction $txn, array $selectedIds): array
    {
        $rows = $txn->vendTransactionItems;

        // No line-item rows recorded — treat the whole transaction as one implicit item.
        if ($rows->isEmpty()) {
            return [[
                'vend_transaction_item_id' => null,
                'product_id' => $txn->product_id,
                'product_name' => $txn->product?->name ?? 'Purchase',
                'vend_channel_code' => $txn->vend_channel_code,
                'unit_price_cents' => (int) $txn->amount,
                'had_channel_error' => (bool) $txn->vend_channel_error_id,
                'vend_channel_error_code' => $txn->vend_channel_error_id ? (string) $txn->vend_channel_error_id : null,
                'channel_error_weightage' => null,
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
            return [
                'vend_transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? ($item->product_name ?? 'Item'),
                'vend_channel_code' => $item->vend_channel_code,
                'unit_price_cents' => (int) ($item->unit_price_amount ?? 0),
                'had_channel_error' => (bool) $item->vend_channel_error_id,
                'vend_channel_error_code' => $item->vend_channel_error_code,
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
