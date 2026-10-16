<?php

namespace App\Services\SmartFreezer;

use App\Jobs\RefundOmiseJob;
use App\Models\PaymentGatewayLog;
use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A smart freezer took a PayNow (Omise) payment but never opened its door (Brian, 2026-10-16): an AI
 * camera was down after payment, or the unlock was never authorised. The freezer reports it on
 * POST /api/v1/vends/{code}/orders/{orderId}/unfulfilled; this refunds the charge in full through
 * RefundOmiseJob (source omise_door_not_opened), after checking the claim against what mark1 knows.
 *
 * Without it the payment stays a sale: the freezer confirms the order before unlocking (so the
 * 10-minute no-dispense refund never fires), sends no TRADE for an aborted session, and the 00:01
 * missing-trade marker books it as code 99 — revenue, never refunded.
 *
 * Refused (the money stays put, a person decides) when the order is not this machine's, not an
 * approved Omise charge, a different amount, older than the allowed age, or there is any sign the
 * door session happened: a TRADE for the order, or a Zijia door video for the session.
 */
class FreezerUnfulfilledRefund
{
    public const QUEUED = 'refund_queued';

    public const ALREADY = 'already_refunded';

    /**
     * @return array{status: string, http: int, error?: string}
     */
    public function request(Vend $vend, string $orderId, string $sessionRef, int $amountCents, string $reason): array
    {
        $refuse = fn (int $http, string $error) => $this->refused($vend, $orderId, $sessionRef, $reason, $http, $error);

        if (! $vend->isSmartFreezer()) {
            return $refuse(422, 'not a smart freezer');
        }
        if (! in_array($reason, (array) config('smart_freezer.unfulfilled_refund.reasons'), true)) {
            return $refuse(422, 'reason not refundable');
        }

        $log = PaymentGatewayLog::query()
            ->where('order_id', $orderId)
            ->where(fn ($q) => $q->where('vend_id', $vend->id)->orWhere('vend_code', (string) $vend->code))
            ->with('operatorPaymentGateway.paymentGateway')
            ->first();
        if (! $log) {
            return $refuse(404, 'unknown order for this machine');
        }
        if ((int) $log->status === PaymentGatewayLog::STATUS_REFUND) {
            return ['status' => self::ALREADY, 'http' => 200];
        }
        if ((int) $log->status !== PaymentGatewayLog::STATUS_APPROVE) {
            return $refuse(409, 'payment not approved');
        }
        if (! $log->operatorPaymentGateway?->paymentGateway?->supportsApiRefund()) {
            return $refuse(409, 'gateway cannot refund by API');
        }
        // payment_gateway_logs.amount is DOLLARS; the freezer speaks cents.
        if ((int) round((float) $log->amount * 100) !== $amountCents) {
            return $refuse(409, 'amount does not match the payment');
        }
        $maxAge = (int) config('smart_freezer.unfulfilled_refund.max_age_minutes');
        if (! $log->approved_at || $log->approved_at->lt(now()->subMinutes($maxAge))) {
            return $refuse(409, 'payment too old to refund automatically');
        }
        if ($this->tradeArrived($vend, $log)) {
            return $refuse(409, 'the machine reported a sale for this order');
        }
        if ($sessionRef !== '' && SmartFreezerVideo::query()->where('order_no', $sessionRef)->exists()) {
            return $refuse(409, 'a door session was recorded');
        }

        // One refund per order however often the freezer repeats the report.
        if (! Cache::add("freezer-unfulfilled-refund:{$log->order_id}", 1, now()->addDay())) {
            return ['status' => self::QUEUED, 'http' => 202];
        }
        RefundOmiseJob::dispatch($log->order_id, AutoRefundSource::OMISE_DOOR_NOT_OPENED);
        Log::warning('freezer unfulfilled payment refunded', [
            'vend' => $vend->code, 'order_id' => $log->order_id, 'session_ref' => $sessionRef,
            'reason' => $reason, 'amount_cents' => $amountCents,
        ]);

        return ['status' => self::QUEUED, 'http' => 202];
    }

    /** A TRADE for this order: the unified row it filled, or a legacy row under the same order id. */
    private function tradeArrived(Vend $vend, PaymentGatewayLog $log): bool
    {
        return VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->where('is_found_in_transaction', true)
            ->where(fn ($q) => $q->where('payment_gateway_log_id', $log->id)->orWhere('order_id', $log->order_id))
            ->exists();
    }

    /** @return array{status: string, http: int, error: string} */
    private function refused(Vend $vend, string $orderId, string $sessionRef, string $reason, int $http, string $error): array
    {
        Log::warning('freezer unfulfilled refund refused', [
            'vend' => $vend->code, 'order_id' => $orderId, 'session_ref' => $sessionRef, 'reason' => $reason, 'error' => $error,
        ]);

        return ['status' => 'refused', 'http' => $http, 'error' => $error];
    }
}
