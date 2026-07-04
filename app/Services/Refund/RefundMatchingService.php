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
        if ($day === 'today') {
            return ['from' => Carbon::today()->startOfDay(), 'to' => Carbon::now()];
        }

        if ($day === 'yesterday') {
            return ['from' => Carbon::yesterday()->startOfDay(), 'to' => Carbon::yesterday()->endOfDay()];
        }

        // Custom date (YYYY-MM-DD), bounded by the refund eligibility window.
        // Anything outside the window returns null so the form falls through to
        // manual review instead of silently matching old transactions.
        $maxLookback = (int) config('refund.match.max_lookback_days', 14);
        if ($maxLookback > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }

            $earliest = Carbon::today()->subDays($maxLookback)->startOfDay();
            if ($date->lt($earliest) || $date->gt(Carbon::today())) {
                return null;
            }

            // Cap "to" at now for today; full day otherwise.
            $to = $date->isSameDay(Carbon::today()) ? Carbon::now() : $date->copy()->endOfDay();

            return ['from' => $date, 'to' => $to];
        }

        return null;
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
            ->with([
                'vendTransactionItems.product.thumbnail',
                'vendTransactionItems.vendChannel.product.thumbnail',
                'vendTransactionItems.vendChannelError',
                'product.thumbnail',
                'vendChannel.product.thumbnail',
                'vendChannelError',
                'paymentMethod',
            ])
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
        $items = $txn->vendTransactionItems->map(function ($item) use ($ticketedItemIds, $txn) {
            $error = $item->vendChannelError;
            $product = $item->product ?? $item->vendChannel?->product;
            $refunded = (bool) $item->is_refunded || in_array((int) $item->id, $ticketedItemIds, true);
            $itemHasError = (bool) $item->vend_channel_error_id && $this->isRealChannelError($item->vend_channel_error_code);
            // Fall back to items_json / header when the pre-created item row never
            // got its channel error backfilled (see fallbackChannelError()).
            $fallback = $itemHasError ? null : $this->fallbackChannelError($txn, $item->vend_channel_code);
            return [
                'vend_transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? ($item->product_name ?? ($item->vend_channel_code ? 'Channel ' . $item->vend_channel_code : 'Item')),
                'product_sku' => $product?->code ?? $item->vendChannel?->sku_code ?? $item->vend_channel_code,
                'product_image_url' => $product?->thumbnail?->full_url,
                'vend_channel_code' => $item->vend_channel_code,
                // unit_price_amount can be 0/unset; fall back to the channel's price
                'unit_price_cents' => (int) ($item->unit_price_amount ?: ($item->vendChannel?->amount ?? 0)),
                'had_channel_error' => $itemHasError || $fallback !== null,
                'vend_channel_error_code' => $item->vend_channel_error_code ?? ($fallback['code'] ?? null),
                'channel_error_desc' => $error?->desc ?? ($fallback['desc'] ?? null),
                'channel_error_weightage' => $error?->weightage ?? ($fallback['weightage'] ?? null),
                'is_refunded' => $refunded,
            ];
        })->values()->all();

        // No line-item rows (common for card-terminal sales) — synthesize one from the
        // transaction's own product / channel so the customer still sees what they bought.
        if (empty($items)) {
            $product = $txn->product ?? $txn->vendChannel?->product;
            $headerHasError = (bool) $txn->vend_channel_error_id && $this->isRealChannelError($txn->vendChannelError?->code);
            $fallback = $headerHasError ? null : $this->fallbackChannelError($txn, $txn->vend_channel_code);
            $items = [[
                'vend_transaction_item_id' => null,
                'product_id' => $txn->product_id,
                'product_name' => $product?->name ?? ($txn->vend_channel_code ? 'Channel ' . $txn->vend_channel_code : 'Purchase'),
                'product_sku' => $product?->code ?? $txn->vendChannel?->sku_code ?? $txn->vend_channel_code,
                'product_image_url' => $product?->thumbnail?->full_url,
                'vend_channel_code' => $txn->vend_channel_code,
                'unit_price_cents' => (int) $txn->amount,
                'had_channel_error' => $headerHasError || $fallback !== null,
                'vend_channel_error_code' => $txn->vendChannelError?->code ?? ($fallback['code'] ?? null),
                'channel_error_desc' => $txn->vendChannelError?->desc ?? ($fallback['desc'] ?? null),
                'channel_error_weightage' => $txn->vendChannelError?->weightage ?? ($fallback['weightage'] ?? null),
                'is_refunded' => (bool) $txn->is_refunded,
            ]];
        }

        return [
            'source' => 'transaction',
            'vend_transaction_id' => $txn->id,
            'payment_gateway_log_id' => $txn->payment_gateway_log_id,
            'datetime' => optional($txn->transaction_datetime)->toDateTimeString(),
            'datetime_label' => optional($txn->transaction_datetime)->format('ymd h:i A'),
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
            'datetime_label' => optional($log->approved_at)->format('ymd h:i A'),
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
                'product_sku' => null,
                'product_image_url' => null,
                'vend_channel_code' => null,
                'unit_price_cents' => (int) $log->amount,
                'had_channel_error' => isset($log->is_dispensed) ? ($log->is_dispensed === false) : false,
                'vend_channel_error_code' => null,
                'channel_error_desc' => null,
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

    /**
     * Whether a channel-error code represents a GENUINE malfunction. Code 0 / "00" is
     * "No Malfunction" (dispensed fine) and must NOT count as evidence of failure.
     */
    public function isRealChannelError(?string $code): bool
    {
        $c = trim((string) $code);
        return $c !== '' && ltrim($c, '0') !== '';
    }

    /** @var array<int, \App\Models\VendChannelError|null> */
    protected array $channelErrorCache = [];

    /**
     * Per-channel channel-error fallback for the refund read path.
     *
     * The canonical per-item error lives on
     * vend_transaction_items.vend_channel_error_id. But gateway / unified
     * transactions PRE-CREATE their item rows with a NULL error and only backfill
     * them when the machine TRADE rebuilds them; if that rebuild never lands, the
     * item row reads NULL even though the transaction really did record the error
     * (e.g. Sensor error 7 on a multiple-purchase). The reliable per-channel copy
     * survives in vend_transactions.items_json — each child carries
     * vendChannelCode + errorCode + vendChannelErrorID — with the header
     * vend_channel_error_id as a last resort.
     *
     * Returns null when no real channel error can be resolved for this channel,
     * so callers only ever ADD an error signal, never remove one.
     *
     * @return array{id:int, code:mixed, desc:?string, weightage:mixed}|null
     */
    public function fallbackChannelError(VendTransaction $txn, $channelCode): ?array
    {
        // 1) items_json child matching this channel.
        $children = $txn->items_json;
        if (is_array($children) && $channelCode !== null) {
            foreach ($children as $child) {
                if (!is_array($child)) {
                    continue;
                }
                if ((string) ($child['vendChannelCode'] ?? '') !== (string) $channelCode) {
                    continue;
                }
                $errId = $child['vendChannelErrorID'] ?? null;
                $errCode = $child['errorCode'] ?? null;
                if ($errId && $this->isRealChannelError((string) $errCode)) {
                    return $this->channelErrorPayload((int) $errId, $errCode);
                }
                return null; // channel matched but no real error → nothing to add
            }
        }

        // 2) header-level error, only when it belongs to this channel (or there is
        //    no channel code to disambiguate against).
        if ($txn->vend_channel_error_id
            && ($channelCode === null || (string) $txn->vend_channel_code === (string) $channelCode)) {
            $hdr = $txn->vendChannelError;
            if ($hdr && $this->isRealChannelError((string) $hdr->code)) {
                return ['id' => (int) $txn->vend_channel_error_id, 'code' => $hdr->code, 'desc' => $hdr->desc, 'weightage' => $hdr->weightage];
            }
        }

        return null;
    }

    /** Resolve a VendChannelError (cached) into the payload shape callers expect. */
    protected function channelErrorPayload(int $id, $codeFallback = null): array
    {
        if (!array_key_exists($id, $this->channelErrorCache)) {
            $this->channelErrorCache[$id] = \App\Models\VendChannelError::find($id);
        }
        $e = $this->channelErrorCache[$id];

        return [
            'id' => $id,
            'code' => $e?->code ?? $codeFallback,
            'desc' => $e?->desc,
            'weightage' => $e?->weightage,
        ];
    }

    public function isAutoRefundTerminal(?string $cashlessMfg): bool
    {
        if (!$cashlessMfg) {
            return false;
        }
        return in_array($cashlessMfg, (array) config('refund.auto_refund_terminals', ['Nayax']), true);
    }
}
