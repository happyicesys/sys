<?php

namespace App\Services\Payment;

use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use Illuminate\Http\Client\Response;

/**
 * Checks an inbound gateway webhook against the gateway's OWN record before
 * mark1 acts on it (audit M3-01). Omise does not sign webhooks; its documented
 * verification is "fetch the object by id with your secret key and trust that".
 *
 * Only the events that move money or goods are checked - an APPROVE (dispense)
 * and a REFUND (is_refunded). Pending / declined events are skipped. Fiuu
 * verifies its own signature in the controller and Midtrans is not in use, so
 * both are skipped here rather than half-verified.
 *
 * Pure function of (webhook, log): no writes, no dispatch. Mode handling
 * (log vs enforce) lives in the caller - see config/payment.php.
 */
class WebhookVerifier
{
    /** Omise charge states that mean "the customer's money was taken". */
    private const OMISE_PAID_STATUS = 'successful';

    public function verify(string $company, array $input, PaymentGatewayLog $log, int $status): WebhookVerdict
    {
        if (! in_array($status, [PaymentGatewayLog::STATUS_APPROVE, PaymentGatewayLog::STATUS_REFUND], true)) {
            return WebhookVerdict::skipped('event moves no money');
        }

        return match ($company) {
            'omise' => $this->verifyOmise($input, $log, $status),
            'fiuu' => WebhookVerdict::skipped('fiuu is signature-verified in the controller'),
            default => WebhookVerdict::skipped("no verifier for {$company}"),
        };
    }

    private function verifyOmise(array $input, PaymentGatewayLog $log, int $status): WebhookVerdict
    {
        $gateway = $log->operatorPaymentGateway;
        if (! $gateway || ! $gateway->key2) {
            return WebhookVerdict::unverifiable('log has no Omise secret key');
        }

        $data = $input['data'] ?? [];
        $object = $data['object'] ?? null;

        // The charge to ask Omise about: the event's own charge, or for a
        // refund event the charge it belongs to (data.charge).
        $chargeId = $status === PaymentGatewayLog::STATUS_REFUND
            ? ($data['charge'] ?? null)
            : ($object === 'charge' ? ($data['id'] ?? null) : null);

        if (! is_string($chargeId) || ! str_starts_with($chargeId, 'chrg_')) {
            // A "source" event can carry successful too, but it names no charge -
            // the charge.complete event that follows is the one that is checked.
            return WebhookVerdict::mismatch('event names no charge id', ['object' => $object, 'data_id' => $data['id'] ?? null]);
        }

        $expectedAmount = $this->expectedMinorAmount($log);
        $timeout = (int) config('payment.webhook_verify_timeout', 8);

        try {
            /** @var Response $response */
            $response = (new Omise($gateway->key1, $gateway->key2))->getCharge($chargeId, $timeout);
        } catch (\Throwable $e) {
            return WebhookVerdict::unverifiable('omise api unreachable: '.$e->getMessage(), ['charge' => $chargeId]);
        }

        if ($response->status() === 404) {
            return WebhookVerdict::mismatch('omise has no such charge', ['charge' => $chargeId]);
        }
        if (! $response->successful()) {
            return WebhookVerdict::unverifiable('omise api http '.$response->status(), ['charge' => $chargeId]);
        }

        $charge = $response->json();
        $detail = [
            'charge' => $chargeId,
            'omise_status' => $charge['status'] ?? null,
            'omise_amount' => $charge['amount'] ?? null,
            'omise_refunded' => $charge['refunded_amount'] ?? null,
            'omise_order_id' => $charge['metadata']['order_id'] ?? null,
            'expected_amount' => $expectedAmount,
            'order_id' => $log->order_id,
        ];

        if (($charge['metadata']['order_id'] ?? null) !== (string) $log->order_id) {
            return WebhookVerdict::mismatch('charge belongs to a different order', $detail);
        }
        if ((int) ($charge['amount'] ?? -1) !== $expectedAmount) {
            return WebhookVerdict::mismatch('charge amount differs from our order', $detail);
        }

        if ($status === PaymentGatewayLog::STATUS_APPROVE) {
            if (($charge['status'] ?? null) !== self::OMISE_PAID_STATUS) {
                return WebhookVerdict::mismatch('omise does not report the charge as successful', $detail);
            }

            return WebhookVerdict::verified($detail);
        }

        // REFUND: Omise must show money actually returned on this charge, and
        // the refund object the webhook names must exist on it when given.
        $refundedAmount = (int) ($charge['refunded_amount'] ?? 0);
        if ($refundedAmount <= 0) {
            return WebhookVerdict::mismatch('omise shows nothing refunded on this charge', $detail);
        }
        $refundId = $data['id'] ?? null;
        if (is_string($refundId) && str_starts_with($refundId, 'rfnd_')) {
            $ids = array_column($charge['refunds']['data'] ?? [], 'id');
            if ($ids !== [] && ! in_array($refundId, $ids, true)) {
                return WebhookVerdict::mismatch('refund id is not on the charge', $detail + ['refund' => $refundId]);
            }
        }

        return WebhookVerdict::verified($detail);
    }

    /** payment_gateway_logs.amount is major units; Omise amounts are minor units per the operator's currency. */
    private function expectedMinorAmount(PaymentGatewayLog $log): int
    {
        $exponent = $log->operatorPaymentGateway?->operator?->country?->currency_exponent ?? 2;

        return (int) round((float) $log->amount * pow(10, $exponent));
    }
}
