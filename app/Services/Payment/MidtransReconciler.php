<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Midtrans;
use App\Services\Refund\OmiseRefundRecorder;
use App\Support\AutoRefundSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Catches a final Midtrans notification that never reached us (2026-11-04): asks Midtrans about each QR
 * that expired while the log still reads PENDING, and writes down what it says.
 *
 * It never dispenses and never creates a sale: the customer left long ago. A payment found here is
 * status APPROVE with approved_at NULL, the same as a notification past the 210 s cutoff, and goes on the
 * Paid, not served list (PaymentGatewayLog::scopePaidNotServed) for a person to look at.
 *
 * Each write is `UPDATE ... WHERE status = PENDING`, so a notification racing it cannot apply twice, and
 * only QRs expired over GRACE_MINUTES ago are asked about, so none can still be inside the 210 s cutoff.
 */
class MidtransReconciler
{
    public const GRACE_MINUTES = 5;

    /** Midtrans quotes expiry_time in Jakarta time, whatever the server's zone. */
    private const MIDTRANS_ZONE = 'Asia/Jakarta';

    /** For a row whose response names no expiry: the longest QR ever made (120000 s = 33.3 h). */
    private const LONGEST_QR_HOURS = 34;

    /** Consecutive API failures that end the run; the next run tries again. */
    private const MAX_API_FAILURES = 3;

    public function __construct(private OmiseRefundRecorder $recorder) {}

    /**
     * @return array{counts: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public function run(bool $apply, int $days, int $limit, ?string $orderId = null): array
    {
        $counts = ['asked' => 0, 'paid' => 0, 'declined' => 0, 'refunded' => 0, 'left' => 0, 'raced' => 0];
        $rows = [];
        $failures = 0;

        $gatewayIds = PaymentGateway::where('name', 'midtrans')->pluck('id');
        if ($gatewayIds->isEmpty()) {
            return compact('counts', 'rows');
        }

        $candidates = PaymentGatewayLog::query()
            ->with('operatorPaymentGateway')
            ->whereIn('payment_gateway_id', $gatewayIds)
            ->where('status', PaymentGatewayLog::STATUS_PENDING)
            ->when(
                $orderId,
                fn ($q) => $q->where('order_id', $orderId),
                fn ($q) => $q->where('created_at', '>=', now()->subDays($days)),
            )
            ->lazyByIdDesc(200, 'id');

        foreach ($candidates as $log) {
            if ($counts['asked'] >= $limit || $failures >= self::MAX_API_FAILURES) {
                break;
            }
            if ($this->expiresAt($log)->addMinutes(self::GRACE_MINUTES)->isFuture()) {
                continue;
            }

            $counts['asked']++;
            [$action, $note, $body] = $this->ask($log);
            $failures = $action === 'api_error' ? $failures + 1 : 0;

            $outcome = $action === 'left' || $action === 'api_error' ? 'left' : $action;
            if ($outcome !== 'left' && $apply && ! $this->write($log, $action, $body)) {
                $outcome = 'raced';
            }
            $counts[$outcome]++;

            $rows[] = ['order_id' => $log->order_id, 'vend_code' => $log->vend_code, 'amount' => $log->amount, 'midtrans' => $body['transaction_status'] ?? null, 'action' => $outcome, 'note' => $note];
            Log::log($outcome === 'paid' ? 'warning' : 'info', 'payment.midtrans.reconcile', end($rows) + ['apply' => $apply]);
        }

        return compact('counts', 'rows');
    }

    /** @return array{0: string, 1: ?string, 2: array<string, mixed>} action, note, Midtrans's answer */
    private function ask(PaymentGatewayLog $log): array
    {
        $serverKey = $log->operatorPaymentGateway?->key1;
        if (! $serverKey) {
            return ['left', 'no server key on the log', []];
        }

        try {
            $response = (new Midtrans($serverKey))->getTransactionStatus($log->order_id, (int) config('payment.midtrans_reconcile.timeout', 8));
        } catch (\Throwable $e) {
            return ['api_error', 'midtrans api unreachable: '.$e->getMessage(), []];
        }
        if (! $response->successful() && $response->status() !== 404) {
            return ['api_error', 'midtrans api http '.$response->status(), []];
        }

        $body = (array) $response->json();
        $state = $body['transaction_status'] ?? null;
        if (! $state || (string) ($body['order_id'] ?? '') !== (string) $log->order_id) {
            return ['left', 'midtrans has no such order', []];
        }

        return match ($state) {
            'settlement', 'capture' => (float) ($body['gross_amount'] ?? -1) === (float) $log->amount
                ? ['paid', null, $body]
                : ['left', 'amount differs from our order', $body],
            'expire', 'cancel', 'deny' => ['declined', null, $body],
            'refund', 'partial_refund' => ['refunded', null, $body],
            default => ['left', "midtrans says {$state}", $body],
        };
    }

    private function write(PaymentGatewayLog $log, string $action, array $body): bool
    {
        $payload = $body + ['reconciled_by' => 'payment:reconcile-midtrans', 'reconciled_at' => now()->toIso8601String()];
        $status = match ($action) {
            'paid' => PaymentGatewayLog::STATUS_APPROVE,
            'declined' => PaymentGatewayLog::STATUS_DECLINE,
            'refunded' => PaymentGatewayLog::STATUS_REFUND,
        };

        $changed = PaymentGatewayLog::whereKey($log->id)
            ->where('status', PaymentGatewayLog::STATUS_PENDING)
            ->update([
                'status' => $status,
                'ref_id' => $body['transaction_id'] ?? $log->ref_id,
                'method' => $log->method ?: ($body['acquirer'] ?? null),
                'response' => json_encode($payload),
            ]);
        if (! $changed) {
            return false;
        }

        if ($action === 'refunded') {
            $this->recorder->record($log->fresh(), $payload, AutoRefundSource::MIDTRANS_EXTERNAL);
        }

        return true;
    }

    private function expiresAt(PaymentGatewayLog $log): Carbon
    {
        $expiry = $log->response['expiry_time'] ?? null;
        if (is_string($expiry) && $expiry !== '') {
            try {
                return Carbon::parse($expiry, self::MIDTRANS_ZONE)->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
            }
        }

        return $log->created_at->copy()->addHours(self::LONGEST_QR_HOURS);
    }
}
