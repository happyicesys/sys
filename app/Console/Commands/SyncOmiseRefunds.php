<?php

namespace App\Console\Commands;

use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use App\Services\Refund\OmiseRefundRecorder;
use App\Support\AutoRefundSource;
use Illuminate\Console\Command;

/**
 * Reconcile mark1 against Omise's OWN refund records — catches refunds made
 * outside mark1 (Omise dashboard, dispute/chargeback accepted as a refund)
 * whose `refund.create` webhook was dropped or never mapped (pre-2026-08-23 the
 * handler required metadata.order_id, which external refunds don't carry).
 *
 *   refund:sync-omise --charge=chrg_xxx            one charge (GET /charges/{id})
 *   refund:sync-omise --from=2026-08-01 [--to=]    every refund on every live
 *                                                  Omise account in the window
 *                                                  (GET /refunds, paged)
 *
 * DRY-RUN BY DEFAULT — prints what would be marked. --apply records each hit via
 * OmiseRefundRecorder (log → REFUND, vend_transaction → is_refunded +
 * auto_refund_source=omise_external + REFUNDED, open tickets crossed).
 * Logs already at REFUND are skipped, so re-running is safe.
 */
class SyncOmiseRefunds extends Command
{
    protected $signature = 'refund:sync-omise
        {--charge= : a single Omise charge id (chrg_…)}
        {--from= : list refunds created at/after this date (Y-m-d)}
        {--to= : list refunds created before this date (Y-m-d); default now}
        {--apply : write; without it only reports}';

    protected $description = 'Reconcile payment_gateway_logs / vend_transactions against refunds Omise holds (dashboard, dispute) — dry-run unless --apply';

    public function handle(OmiseRefundRecorder $recorder): int
    {
        $apply = (bool) $this->option('apply');

        if ($charge = $this->option('charge')) {
            return $this->syncCharge($charge, $apply, $recorder);
        }
        if ($from = $this->option('from')) {
            return $this->syncWindow($from, $this->option('to') ?: now()->toDateString(), $apply, $recorder);
        }

        $this->error('Give --charge=chrg_… or --from=Y-m-d');

        return self::FAILURE;
    }

    protected function syncCharge(string $chargeId, bool $apply, OmiseRefundRecorder $recorder): int
    {
        $log = PaymentGatewayLog::where('ref_id', $chargeId)->orderByDesc('id')->first();
        if (! $log) {
            $this->error("No payment_gateway_log with ref_id {$chargeId}");

            return self::FAILURE;
        }

        $client = new Omise($log->operatorPaymentGateway->key1, $log->operatorPaymentGateway->key2);
        $resp = $client->getCharge($chargeId);
        if ($resp->failed()) {
            $this->error("Omise GET /charges/{$chargeId} failed: HTTP {$resp->status()} {$resp->body()}");

            return self::FAILURE;
        }
        $charge = $resp->json();
        $refunded = (int) ($charge['refunded_amount'] ?? 0);
        $amount = (int) ($charge['amount'] ?? 0);

        $this->table(['log id', 'order_id', 'log status', 'Omise status', 'amount', 'refunded_amount'], [[
            $log->id, $log->order_id, $log->status, $charge['status'] ?? '?', $amount, $refunded,
        ]]);

        if ($refunded <= 0) {
            $this->line('Omise shows no refund on this charge — nothing to do.');

            return self::SUCCESS;
        }
        if ((int) $log->status === PaymentGatewayLog::STATUS_REFUND) {
            $this->line('Already recorded as REFUND in mark1 — nothing to do.');

            return self::SUCCESS;
        }
        if ($refunded < $amount) {
            $this->warn("Partial refund ({$refunded}/{$amount} cents) — recorded as refunded; review the amount by hand.");
        }
        if (! $apply) {
            $this->line('DRY-RUN: would record as refunded (omise_external). Re-run with --apply.');

            return self::SUCCESS;
        }

        $recorder->record($log, $charge, AutoRefundSource::OMISE_EXTERNAL);
        $this->info("Recorded: log {$log->id} → REFUND, vend_transaction + tickets synced (omise_external).");

        return self::SUCCESS;
    }

    protected function syncWindow(string $from, string $to, bool $apply, OmiseRefundRecorder $recorder): int
    {
        // One Omise account per distinct secret key — several operators share
        // the same account, so dedupe or we list the same refunds N times.
        $accounts = OperatorPaymentGateway::query()
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'omise'))
            ->where('type', OperatorPaymentGateway::TYPE_PRODUCTION)
            ->get()
            ->unique('key2');

        $rows = [];
        $seenCharges = [];
        foreach ($accounts as $account) {
            $client = new Omise($account->key1, $account->key2);
            $offset = 0;
            do {
                $resp = $client->listRefunds([
                    'from' => $from.'T00:00:00Z',
                    'to' => $to.'T00:00:00Z',
                    'limit' => 100,
                    'offset' => $offset,
                    'order' => 'chronological',
                ]);
                if ($resp->failed()) {
                    $this->error('GET /refunds failed for account key …'.substr($account->key2, -6).": HTTP {$resp->status()}");
                    break;
                }
                $page = $resp->json();
                foreach (($page['data'] ?? []) as $refund) {
                    $chargeId = $refund['charge'] ?? null;
                    if (! $chargeId || isset($seenCharges[$chargeId])) {
                        continue;
                    }
                    $seenCharges[$chargeId] = true;

                    $log = PaymentGatewayLog::where('ref_id', $chargeId)->orderByDesc('id')->first();
                    if (! $log) {
                        continue; // not ours (other instance / sandbox / pre-mark1)
                    }
                    if ((int) $log->status === PaymentGatewayLog::STATUS_REFUND) {
                        continue; // already recorded (our own API refund, or a mapped webhook)
                    }
                    $rows[] = [$log->id, $log->order_id, $chargeId, $refund['amount'] ?? '?', $refund['created_at'] ?? '?', ! empty($refund['metadata']['order_id']) ? 'mark1' : 'external'];
                    if ($apply) {
                        $recorder->record($log, $refund, AutoRefundSource::OMISE_EXTERNAL);
                    }
                }
                $offset += 100;
                $more = ($page['total'] ?? 0) > $offset;
            } while ($more);
        }

        $this->info(($apply ? 'APPLIED' : 'DRY-RUN')." — {$from} ≤ refund.created_at < {$to}: ".count($rows).' refund(s) Omise holds that mark1 had NOT recorded');
        $this->table(['log id', 'order_id', 'charge', 'cents', 'refund created_at', 'made by'], $rows);
        if (! $apply && $rows) {
            $this->line('Re-run with --apply to record them (omise_external).');
        }

        return self::SUCCESS;
    }
}
