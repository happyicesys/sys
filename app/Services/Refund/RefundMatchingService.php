<?php

namespace App\Services\Refund;

use App\Models\PaymentGatewayLog;
use App\Models\RefundTicket;
use App\Models\RefundTicketItem;
use App\Models\Vend;
use App\Models\VendTransaction;
use Carbon\Carbon;

/**
 * Resolves a machine from the scanned machineID (= vends.code) and finds the
 * candidate transactions for a chosen day + entered amount.
 *
 * Public/unauthenticated context: all reads use withoutGlobalScopes() because
 * the operator-filter global scopes on Vend / VendTransaction depend on an
 * authenticated user, which the customer form does not have.
 */
class RefundMatchingService
{
    public function resolveMachine(string $machineID): ?Vend
    {
        return Vend::withoutGlobalScopes()->where('code', $machineID)->first();
    }

    /**
     * @return array{from: Carbon, to: Carbon}|null
     */
    public function dayRange(string $day): ?array
    {
        $allowed = config('refund.match.days', ['today', 'yesterday']);
        if (!in_array($day, $allowed, true)) {
            return null;
        }

        if ($day === 'yesterday') {
            return ['from' => Carbon::yesterday()->startOfDay(), 'to' => Carbon::yesterday()->endOfDay()];
        }

        return ['from' => Carbon::today()->startOfDay(), 'to' => Carbon::now()];
    }

    /**
     * Find candidate transactions for the form to display.
     *
     * @return array{vend: ?Vend, candidates: array<int, array>}
     */
    public function candidates(string $machineID, string $day, int $amountCents): array
    {
        $vend = $this->resolveMachine($machineID);
        $range = $this->dayRange($day);

        if (!$vend || !$range) {
            return ['vend' => $vend, 'candidates' => []];
        }

        $tolerance = (int) config('refund.match.amount_tolerance_cents', 0);
        $limit = (int) config('refund.match.max_candidates', 12);
        $min = $amountCents - $tolerance;
        $max = $amountCents + $tolerance;

        // Double-refund guard: items / gateway charges already covered by an
        // active (non-rejected) refund ticket are treated as already refunded.
        [$ticketedItemIds, $ticketedLogIds] = $this->activeTicketCoverage();

        $candidates = [];

        // ---- Source A: canonical sales (by vend_id) ----
        $txns = VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->whereBetween('transaction_datetime', [$range['from'], $range['to']])
            ->whereBetween('amount', [$min, $max])
            ->where(function ($q) {
                $q->whereNull('is_refunded')->orWhere('is_refunded', false);
            })
            ->with(['vendTransactionItems.product', 'vendTransactionItems.vendChannelError', 'paymentMethod'])
            ->orderByDesc('transaction_datetime')
            ->limit($limit)
            ->get();

        $seenGatewayLogIds = [];

        foreach ($txns as $txn) {
            if ($txn->payment_gateway_log_id) {
                $seenGatewayLogIds[] = $txn->payment_gateway_log_id;
            }
            $candidate = $this->buildTransactionCandidate($txn, $ticketedItemIds);
            // skip transactions where every item is already refunded / ticketed
            $allCovered = !empty($candidate['items']) && collect($candidate['items'])->every(fn ($i) => $i['is_refunded'] === true);
            if (!$allCovered) {
                $candidates[] = $candidate;
            }
        }

        // ---- Source B: gateway charges (by vend_code) not already covered ----
        $logs = PaymentGatewayLog::where('vend_code', $machineID)
            ->whereBetween('approved_at', [$range['from'], $range['to']])
            ->whereBetween('amount', [$min, $max])
            ->whereIn('status', [PaymentGatewayLog::STATUS_APPROVE, PaymentGatewayLog::STATUS_REFUND])
            ->orderByDesc('approved_at')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            if (in_array($log->id, $seenGatewayLogIds, true)) {
                continue; // already represented by a vend_transaction candidate
            }
            if (in_array($log->id, $ticketedLogIds, true)) {
                continue; // already covered by an active ticket
            }
            $candidates[] = $this->buildGatewayCandidate($log);
        }

        return ['vend' => $vend, 'candidates' => $candidates];
    }

    /**
     * Item ids and gateway-log ids already covered by an active (non-rejected) ticket.
     *
     * @return array{0: array<int,int>, 1: array<int,int>}
     */
    protected function activeTicketCoverage(): array
    {
        $itemIds = RefundTicketItem::query()
            ->whereNotNull('vend_transaction_item_id')
            ->whereHas('ticket', fn ($q) => $q->where('status', '!=', RefundTicket::STATUS_REJECTED))
            ->pluck('vend_transaction_item_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $logIds = RefundTicket::query()
            ->whereNotNull('payment_gateway_log_id')
            ->whereNull('vend_transaction_id')
            ->where('status', '!=', RefundTicket::STATUS_REJECTED)
            ->pluck('payment_gateway_log_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        return [$itemIds, $logIds];
    }

    protected function buildTransactionCandidate(VendTransaction $txn, array $ticketedItemIds = []): array
    {
        $items = $txn->vendTransactionItems->map(function ($item) use ($ticketedItemIds) {
            $error = $item->vendChannelError;
            $refunded = (bool) $item->is_refunded || in_array((int) $item->id, $ticketedItemIds, true);
            return [
                'vend_transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? ($item->product_name ?? 'Item'),
                'vend_channel_code' => $item->vend_channel_code,
                'unit_price_cents' => (int) ($item->unit_price_amount ?? 0),
                'had_channel_error' => (bool) $item->vend_channel_error_id,
                'vend_channel_error_code' => $item->vend_channel_error_code,
                'channel_error_weightage' => $error?->weightage,
                'is_refunded' => $refunded,
            ];
        })->values()->all();

        return [
            'source' => 'transaction',
            'vend_transaction_id' => $txn->id,
            'payment_gateway_log_id' => $txn->payment_gateway_log_id,
            'datetime' => optional($txn->transaction_datetime)->toDateTimeString(),
            'amount' => round($txn->amount / 100, 2),
            'amount_cents' => (int) $txn->amount,
            'payment_method' => $txn->paymentMethod?->name,
            'payment_channel' => $this->classifyChannel($txn->cashless_mfg, $txn->payment_gateway_log_id),
            'is_auto_refund_channel' => $this->isAutoRefundTerminal($txn->cashless_mfg),
            'already_refunded' => (bool) $txn->is_refunded,
            'items' => $items,
        ];
    }

    protected function buildGatewayCandidate(PaymentGatewayLog $log): array
    {
        // status may come back as a string from the driver (no int cast on the model) — compare loosely.
        $alreadyRefunded = ((int) $log->status === PaymentGatewayLog::STATUS_REFUND);

        return [
            'source' => 'gateway',
            'vend_transaction_id' => null,
            'payment_gateway_log_id' => $log->id,
            'datetime' => optional($log->approved_at)->toDateTimeString(),
            'amount' => round($log->amount / 100, 2),
            'amount_cents' => (int) $log->amount,
            'payment_method' => $log->method ?: 'QR / PayNow',
            'payment_channel' => RefundTicket::CHANNEL_QR,
            'is_auto_refund_channel' => false,
            'already_refunded' => $alreadyRefunded,
            // gateway-only charge has no item breakdown — treat as a single implicit item
            'items' => [[
                'vend_transaction_item_id' => null,
                'product_id' => null,
                'product_name' => 'Purchase',
                'vend_channel_code' => null,
                'unit_price_cents' => (int) $log->amount,
                'had_channel_error' => isset($log->is_dispensed) ? ($log->is_dispensed === false) : false,
                'vend_channel_error_code' => null,
                'channel_error_weightage' => null,
                'is_refunded' => $alreadyRefunded,
            ]],
        ];
    }

    public function classifyChannel(?string $cashlessMfg, $paymentGatewayLogId): string
    {
        if ($cashlessMfg) {
            return $this->isAutoRefundTerminal($cashlessMfg)
                ? RefundTicket::CHANNEL_NAYAX
                : RefundTicket::CHANNEL_OTHER_POS;
        }
        if ($paymentGatewayLogId) {
            return RefundTicket::CHANNEL_QR;
        }
        return RefundTicket::CHANNEL_UNKNOWN;
    }

    public function isAutoRefundTerminal(?string $cashlessMfg): bool
    {
        if (!$cashlessMfg) {
            return false;
        }
        return in_array($cashlessMfg, (array) config('refund.auto_refund_terminals', ['Nayax']), true);
    }
}
