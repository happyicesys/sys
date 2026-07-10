<?php

namespace App\Jobs;

use App\Models\RefundTicket;
use App\Services\Refund\RefundEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends ONE templated refund email off the queue instead of inline on the web
 * request. Every automatic refund email — the /refund acknowledgement
 * (T_RECEIVED) and the admin workflow emails (Approve / Reject / etc.) — now
 * dispatches this job, so a slow or failing mail transport never blocks the
 * customer submission or the Ops button that triggered it.
 *
 * The actual rendering, delivery gating (REFUND_EMAIL_ENABLED), audit-trail
 * line and thread-root bookkeeping all still live in RefundEmailService::send();
 * this job just runs that same call in the background.
 */
class SendRefundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry a couple of times for transient infrastructure failures (DB/Redis
     * blips resolving the ticket). Note that send() swallows its own mail-send
     * exceptions and never rethrows, so a failed delivery does NOT cause a retry
     * and cannot double-send.
     */
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $refundTicketId,
        public string $templateKey,
    ) {
    }

    public function handle(RefundEmailService $email): void
    {
        $ticket = RefundTicket::find($this->refundTicketId);
        if (!$ticket) {
            Log::warning('SendRefundEmailJob: refund ticket not found', [
                'ticket_id' => $this->refundTicketId,
                'template' => $this->templateKey,
            ]);

            return;
        }

        $email->send($ticket, $this->templateKey);
    }
}
