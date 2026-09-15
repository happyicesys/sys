<?php

namespace App\Jobs;

use App\Models\PaymentGatewayLog;
use App\Services\Payment\WebhookVerdict;
use App\Services\Payment\WebhookVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * `log` mode of config('payment.webhook_verification'): verify a webhook AFTER
 * the controller has already acted on it and only write the verdict to the
 * log, so real traffic can prove the check before it is enforced. Runs on the
 * `low` queue - it is never on the approval path.
 *
 * Grep for `payment.webhook.verify` to read the verdicts; `mismatch` lines are
 * the ones that would have been refused in `enforce` mode.
 */
class VerifyPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $backoff = [30, 120];

    public $timeout = 30;

    public function __construct(
        public readonly int $paymentGatewayLogId,
        public readonly string $company,
        public readonly array $input,
        public readonly int $status,
    ) {
        $this->onQueue('low');
    }

    public function handle(WebhookVerifier $verifier): void
    {
        $log = PaymentGatewayLog::find($this->paymentGatewayLogId);
        if (! $log) {
            return;
        }

        $started = microtime(true);
        $verdict = $verifier->verify($this->company, $this->input, $log, $this->status);
        $context = $verdict->toLogContext() + [
            'mode' => 'log',
            'company' => $this->company,
            'log_id' => $log->id,
            'vend_code' => $log->vend_code,
            'status' => $this->status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        match ($verdict->outcome) {
            WebhookVerdict::MISMATCH => Log::warning('payment.webhook.verify mismatch (not enforced)', $context),
            WebhookVerdict::UNVERIFIABLE => Log::warning('payment.webhook.verify unverifiable', $context),
            default => Log::info('payment.webhook.verify '.$verdict->outcome, $context),
        };

        // Let the retry/backoff have another go at a transient API failure.
        if ($verdict->outcome === WebhookVerdict::UNVERIFIABLE && $this->job && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 120);
        }
    }
}
