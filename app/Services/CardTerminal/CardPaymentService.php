<?php

namespace App\Services\CardTerminal;

use App\Models\CardPaymentIntent;
use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The lifecycle of a card attempt on a remote terminal — the only writer of
 * card_payment_intents (see the state diagram on CardPaymentIntent).
 *
 * The device (FreezerCardController) and the scheduled reconciler both drive
 * intents through here, so every rule has one home:
 *
 *  1. An approval the device will never act on is voided. That covers a cancel
 *     that raced the tap, a send that timed out but reached the terminal, and
 *     an attempt the device abandoned. The customer is never charged for a
 *     door that did not open.
 *  2. A void is refused once the goods are presumed released: after capture,
 *     or past `device_void_window_minutes` from approval.
 *  3. One provider call at a time per intent (cache lock), so the device's
 *     poll and the reconciler cannot both void the same money.
 */
class CardPaymentService
{
    private const LOCK_SECONDS = 20;

    private const LOCK_WAIT_SECONDS = 10;

    private const TERMINAL_STATUS_CACHE_SECONDS = 10;

    public function __construct(
        private readonly RemoteCardTerminalGatewayFactory $gateways,
        private readonly CardTerminalEventLog $events,
    ) {}

    /**
     * Starts (or returns) the attempt for this device reference. Idempotent: a
     * retried request with the same reference never charges twice.
     *
     * @throws CardTerminalException when the machine has no active terminal
     * @throws DomainException when the reference was already used for another amount
     */
    public function authorize(Vend $vend, string $reference, int $cents): CardPaymentIntent
    {
        $terminal = RemoteCardTerminal::activeForVend($vend)
            ?? throw CardTerminalException::notConfigured("no active remote terminal on vend {$vend->code}");

        $existing = CardPaymentIntent::query()->where('vend_id', $vend->id)->where('reference', $reference)->first();
        if ($existing) {
            if ($existing->amount_cents !== $cents) {
                throw new DomainException("reference {$reference} already used for {$existing->amount_cents}c");
            }

            return $existing;
        }

        // createOrFirst: two concurrent requests for one reference land on one row
        // (unique vend_id + reference); only the creator sends it to the terminal.
        $intent = CardPaymentIntent::createOrFirst(['vend_id' => $vend->id, 'reference' => $reference], [
            'remote_card_terminal_id' => $terminal->id,
            'provider' => $terminal->provider,
            'custom_order_id' => CardPaymentIntent::customOrderIdFor($vend, $reference),
            'mode' => $this->mode(),
            'amount_cents' => $cents,
            'state' => CardPaymentIntent::STATE_PENDING,
        ]);
        if (! $intent->wasRecentlyCreated) {
            return $intent;
        }

        return $this->locked($intent, function (CardPaymentIntent $intent) use ($terminal) {
            $gateway = $this->gateways->for($terminal);
            try {
                $intent->mode === CardPaymentIntent::MODE_PREAUTH
                    ? $gateway->requestPreauth($terminal, $intent->custom_order_id, $intent->amount_cents)
                    : $gateway->requestSale($terminal, $intent->custom_order_id, $intent->amount_cents);
                $intent->update(['state' => CardPaymentIntent::STATE_PROCESSING]);
            } catch (CardTerminalException $e) {
                $this->failSend($intent, $gateway, $terminal, $e);
            }

            return $intent;
        });
    }

    /**
     * The device's poll: re-queries the provider (throttled) while the attempt
     * is unresolved, then returns the current row.
     */
    public function refresh(CardPaymentIntent $intent): CardPaymentIntent
    {
        if (! $intent->isUnresolved() && $intent->state !== CardPaymentIntent::STATE_VOID_FAILED) {
            return $intent;
        }

        // Throttle on the freshly read row: the caller's copy may predate the last query.
        return $this->locked($intent, fn (CardPaymentIntent $intent) => $this->queriedTooRecently($intent)
            ? $intent
            : $this->advance($intent));
    }

    /**
     * The customer pressed Cancel or the kiosk's card window ran out. Clears the
     * terminal screen; an approval that already landed (or lands later) is voided.
     */
    public function cancel(CardPaymentIntent $intent): CardPaymentIntent
    {
        return $this->locked($intent, function (CardPaymentIntent $intent) {
            if ($intent->state === CardPaymentIntent::STATE_APPROVED) {
                // The tap beat the cancel: the kiosk has already told the customer
                // "cancelled", so this money must go back.
                return $this->voidNow($intent, 'cancelled after approval');
            }
            if (! in_array($intent->state, [CardPaymentIntent::STATE_PENDING, CardPaymentIntent::STATE_PROCESSING], true)) {
                return $intent;
            }

            $intent->update([
                'state' => CardPaymentIntent::STATE_CANCELLING,
                'cancel_requested_at' => Carbon::now(),
            ]);
            $terminal = $intent->terminal;
            try {
                $this->gateways->for($terminal)->cancelActiveRequest($terminal);
            } catch (CardTerminalException $e) {
                // Best effort: the reconciler still watches for a late approval.
                $intent->update(['last_error' => $this->errorText('cancel: '.$e->getMessage())]);
            }

            return $this->advance($intent);
        });
    }

    /**
     * The door opened: the sale is fulfilled. `sale` mode was charged at the tap,
     * so this only closes the void window; `preauth` mode charges here.
     *
     * @throws DomainException when the attempt is not approved or the amount is wrong
     */
    public function capture(CardPaymentIntent $intent, int $cents): CardPaymentIntent
    {
        return $this->locked($intent, function (CardPaymentIntent $intent) use ($cents) {
            if ($intent->state === CardPaymentIntent::STATE_CAPTURED) {
                return $intent;
            }
            if ($intent->state !== CardPaymentIntent::STATE_APPROVED) {
                throw new DomainException("cannot capture an intent in state {$intent->state}");
            }
            if ($cents <= 0 || $cents > $intent->amount_cents) {
                throw new DomainException("capture {$cents}c outside the approved {$intent->amount_cents}c");
            }

            if ($intent->mode === CardPaymentIntent::MODE_PREAUTH) {
                $terminal = $intent->terminal;
                try {
                    $this->gateways->for($terminal)->capture($terminal, $intent->custom_order_id, $cents);
                } catch (CardTerminalException $e) {
                    $intent->update(['last_error' => $this->errorText('capture: '.$e->getMessage())]);
                    throw $e;
                }
            } elseif ($cents !== $intent->amount_cents) {
                // A sale already took the full amount; a smaller "capture" would
                // need a refund, which this API does not have.
                throw new DomainException("sale mode charged {$intent->amount_cents}c; cannot capture {$cents}c");
            }

            $intent->update([
                'state' => CardPaymentIntent::STATE_CAPTURED,
                'captured_cents' => $cents,
                'captured_at' => Carbon::now(),
                'resolved_at' => Carbon::now(),
            ]);

            return $intent;
        });
    }

    /**
     * The door never opened after an approval: give the money back.
     *
     * @throws DomainException when the goods may already be released
     */
    public function void(CardPaymentIntent $intent): CardPaymentIntent
    {
        return $this->locked($intent, function (CardPaymentIntent $intent) {
            switch ($intent->state) {
                case CardPaymentIntent::STATE_VOIDED:
                case CardPaymentIntent::STATE_DECLINED:
                case CardPaymentIntent::STATE_CANCELLED:
                case CardPaymentIntent::STATE_ERROR:
                    return $intent; // no money held — void is a safe no-op
                case CardPaymentIntent::STATE_PENDING:
                case CardPaymentIntent::STATE_PROCESSING:
                case CardPaymentIntent::STATE_CANCELLING:
                    throw new DomainException('not approved yet — cancel it instead');
                case CardPaymentIntent::STATE_CAPTURED:
                    throw new DomainException('already captured: the goods were released');
            }

            $window = (int) config('payrallel.device_void_window_minutes', 15);
            if ($intent->approved_at && $intent->approved_at->lt(Carbon::now()->subMinutes($window))) {
                throw new DomainException("approved more than {$window} min ago: the goods are presumed released");
            }

            return $this->voidNow($intent, 'device void');
        });
    }

    /** Terminal readiness for the device's card button, cached briefly per terminal. */
    public function terminalStatus(Vend $vend): ?TerminalStatus
    {
        $terminal = RemoteCardTerminal::activeForVend($vend);
        if (! $terminal) {
            return null;
        }

        $cached = Cache::get($this->statusCacheKey($terminal));
        if ($cached instanceof TerminalStatus) {
            return $cached;
        }

        try {
            $status = $this->gateways->for($terminal)->status($terminal);
        } catch (CardTerminalException $e) {
            $status = new TerminalStatus(false, null, ['error' => $e->getMessage()]);
        }
        if ($terminal->last_status_at === null
            || $terminal->last_online !== $status->online
            || $terminal->last_state !== $status->state) {
            $this->events->record('terminal.status', array_filter([
                'online' => $status->online,
                'state' => $status->state,
                'was_online' => $terminal->last_online,
                'was_state' => $terminal->last_state,
                'error' => $status->raw['error'] ?? null,
            ], fn ($v) => $v !== null), $terminal, null, null, $status->online ? 'info' : 'warning');
        }
        $terminal->update([
            'last_online' => $status->online,
            'last_state' => $status->state,
            'last_status_at' => Carbon::now(),
        ]);
        Cache::put($this->statusCacheKey($terminal), $status, self::TERMINAL_STATUS_CACHE_SECONDS);

        return $status;
    }

    /**
     * Scheduled sweep (`card-payments:reconcile`): drives every attempt the
     * device stopped polling to a final answer, voiding approvals nobody will
     * fulfil. Returns the number of intents it touched.
     */
    public function reconcile(): int
    {
        $watch = (int) config('payrallel.late_approval_watch_minutes', 10);
        $ttl = (int) config('payrallel.intent_ttl_seconds', 120);
        $horizon = Carbon::now()->subSeconds($ttl)->subMinutes($watch);
        $touched = 0;

        // lazyById, not each(): the loop changes `state`, the column it filters on, and
        // offset paging would then skip rows.
        CardPaymentIntent::query()
            ->whereIn('state', [...CardPaymentIntent::UNRESOLVED_STATES, CardPaymentIntent::STATE_VOID_FAILED])
            ->lazyById(100)
            ->each(function (CardPaymentIntent $intent) use ($horizon, &$touched) {
                $touched++;
                if ($intent->created_at->lt($horizon)) {
                    $this->locked($intent, fn (CardPaymentIntent $i) => $this->giveUp($i));

                    return;
                }
                $this->locked($intent, fn (CardPaymentIntent $i) => $this->advance($i));
            });

        // A sale the device never confirmed: the charge stands (sale mode took the
        // money at the tap) and the void window has closed, so close it as captured.
        // A preauth in the same position is left for a human — we cannot know
        // whether the door opened.
        $voidWindow = (int) config('payrallel.device_void_window_minutes', 15);
        CardPaymentIntent::query()
            ->where('state', CardPaymentIntent::STATE_APPROVED)
            ->where('mode', CardPaymentIntent::MODE_SALE)
            ->where('approved_at', '<', Carbon::now()->subMinutes($voidWindow))
            ->lazyById(100)
            ->each(function (CardPaymentIntent $intent) use (&$touched) {
                $touched++;
                $intent->update([
                    'state' => CardPaymentIntent::STATE_CAPTURED,
                    'captured_cents' => $intent->amount_cents,
                    'captured_at' => Carbon::now(),
                    'resolved_at' => Carbon::now(),
                    'last_error' => 'closed by reconciler: device never confirmed the door',
                ]);
                Log::warning('Card sale closed without device confirmation', $this->logContext($intent));
            });

        return $touched;
    }

    /** One provider query, and the state change it implies. Caller holds the lock. */
    private function advance(CardPaymentIntent $intent): CardPaymentIntent
    {
        if (! $intent->isUnresolved() && $intent->state !== CardPaymentIntent::STATE_VOID_FAILED) {
            return $intent;
        }
        $terminal = $intent->terminal;
        $gateway = $this->gateways->for($terminal);

        try {
            $txn = $gateway->query($terminal, $intent->custom_order_id);
        } catch (CardTerminalException $e) {
            $intent->update([
                'last_error' => $this->errorText('query: '.$e->getMessage()),
                'query_count' => $intent->query_count + 1,
                'last_queried_at' => Carbon::now(),
            ]);

            return $this->expireIfStale($intent);
        }

        $intent->fill([
            'provider_status' => $txn->providerStatus ?? $txn->status,
            'provider_txn_id' => $txn->providerTxnId ?? $intent->provider_txn_id,
            'payment_method' => $txn->paymentMethod ?? $intent->payment_method,
            'last_response' => $txn->raw,
            'query_count' => $intent->query_count + 1,
            'last_queried_at' => Carbon::now(),
        ]);

        if ($intent->state === CardPaymentIntent::STATE_VOID_FAILED) {
            if ($txn->status === TerminalTransaction::VOIDED) {
                return $this->finish($intent, CardPaymentIntent::STATE_VOIDED, ['voided_at' => Carbon::now()]);
            }
            $intent->save();

            return $txn->isApproved() ? $this->voidNow($intent, 'retry of failed void') : $intent;
        }

        if ($txn->isApproved()) {
            $intent->fill(['approved_at' => $intent->approved_at ?? Carbon::now()]);
            if ($intent->state === CardPaymentIntent::STATE_CANCELLING) {
                $intent->save();

                return $this->voidNow($intent, 'approved after cancel');
            }
            $intent->state = CardPaymentIntent::STATE_APPROVED;
            $intent->save();
            Log::info('Card payment approved', $this->logContext($intent));

            return $intent;
        }

        if ($txn->isFailed() || $txn->status === TerminalTransaction::VOIDED) {
            return $this->finish($intent, $intent->state === CardPaymentIntent::STATE_CANCELLING
                ? CardPaymentIntent::STATE_CANCELLED
                : CardPaymentIntent::STATE_DECLINED);
        }

        $intent->save();

        return $this->expireIfStale($intent);
    }

    /** An attempt past its TTL gets its terminal screen cleared and is watched for a late tap. */
    private function expireIfStale(CardPaymentIntent $intent): CardPaymentIntent
    {
        $ttl = (int) config('payrallel.intent_ttl_seconds', 120);
        if (! in_array($intent->state, [CardPaymentIntent::STATE_PENDING, CardPaymentIntent::STATE_PROCESSING], true)
            || $intent->created_at->gt(Carbon::now()->subSeconds($ttl))) {
            return $intent;
        }

        $intent->update([
            'state' => CardPaymentIntent::STATE_CANCELLING,
            'cancel_requested_at' => Carbon::now(),
            'last_error' => $this->errorText('expired after '.$ttl.'s'),
        ]);
        $terminal = $intent->terminal;
        try {
            $this->gateways->for($terminal)->cancelActiveRequest($terminal);
        } catch (CardTerminalException $e) {
            // The reconciler keeps watching either way.
        }

        return $intent;
    }

    /** Past the late-approval watch: one last look, then stop watching. */
    private function giveUp(CardPaymentIntent $intent): CardPaymentIntent
    {
        $intent = $this->advance($intent);
        if ($intent->isUnresolved()) {
            Log::warning('Card attempt unresolved after the watch window — closed as cancelled', $this->logContext($intent));

            return $this->finish($intent, CardPaymentIntent::STATE_CANCELLED, [
                'last_error' => $this->errorText('unresolved after watch window; last provider status '.($intent->provider_status ?? 'none')),
            ]);
        }
        if ($intent->state === CardPaymentIntent::STATE_VOID_FAILED) {
            Log::error('Card void still failing after the watch window — reconcile by hand', $this->logContext($intent));
        }

        return $intent;
    }

    private function voidNow(CardPaymentIntent $intent, string $why): CardPaymentIntent
    {
        $terminal = $intent->terminal;
        try {
            $this->gateways->for($terminal)->void($terminal, $intent->custom_order_id);
        } catch (CardTerminalException $e) {
            $intent->update([
                'state' => CardPaymentIntent::STATE_VOID_FAILED,
                'last_error' => $this->errorText("void ({$why}): ".$e->getMessage()),
            ]);
            Log::error('Card void failed', $this->logContext($intent) + ['why' => $why, 'error' => $e->getMessage()]);

            return $intent;
        }
        Log::warning('Card payment voided', $this->logContext($intent) + ['why' => $why]);

        return $this->finish($intent, CardPaymentIntent::STATE_VOIDED, ['voided_at' => Carbon::now()]);
    }

    private function failSend(CardPaymentIntent $intent, RemoteCardTerminalGateway $gateway, RemoteCardTerminal $terminal, CardTerminalException $e): void
    {
        if (! $e->mayHaveReachedTerminal) {
            $this->finish($intent, CardPaymentIntent::STATE_ERROR, ['last_error' => $this->errorText($e->getMessage())]);

            return;
        }
        // Timeout or 5xx: the terminal may be showing the request. Treat it as a
        // cancelled attempt that is still watched, so a tap that lands anyway is voided.
        $intent->update([
            'state' => CardPaymentIntent::STATE_CANCELLING,
            'cancel_requested_at' => Carbon::now(),
            'last_error' => $this->errorText('send: '.$e->getMessage()),
        ]);
        try {
            $gateway->cancelActiveRequest($terminal);
        } catch (CardTerminalException) {
            // The reconciler still watches.
        }
        Log::warning('Card request delivery uncertain — watching for a late approval', $this->logContext($intent) + ['error' => $e->getMessage()]);
    }

    private function finish(CardPaymentIntent $intent, string $state, array $extra = []): CardPaymentIntent
    {
        $intent->fill($extra + ['state' => $state, 'resolved_at' => Carbon::now()])->save();

        return $intent;
    }

    private function queriedTooRecently(CardPaymentIntent $intent): bool
    {
        $minMs = (int) config('payrallel.min_query_interval_ms', 1000);

        return $intent->last_queried_at !== null
            && $intent->last_queried_at->diffInMilliseconds(Carbon::now(), true) < $minMs;
    }

    /**
     * Runs $work with this intent locked and freshly re-read, so two callers
     * never act on the same money from stale rows.
     *
     * @template T
     *
     * @param  callable(CardPaymentIntent): T  $work
     * @return T
     */
    private function locked(CardPaymentIntent $intent, callable $work): mixed
    {
        return Cache::lock('card-payment-intent:'.$intent->id, self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn () => $work($intent->fresh()));
    }

    private function mode(): string
    {
        return config('payrallel.mode') === CardPaymentIntent::MODE_PREAUTH
            ? CardPaymentIntent::MODE_PREAUTH
            : CardPaymentIntent::MODE_SALE;
    }

    private function statusCacheKey(RemoteCardTerminal $terminal): string
    {
        return 'remote-card-terminal-status:'.$terminal->id;
    }

    private function errorText(string $text): string
    {
        return mb_substr($text, 0, 255);
    }

    private function logContext(CardPaymentIntent $intent): array
    {
        return [
            'intent_id' => $intent->id,
            'vend_id' => $intent->vend_id,
            'order' => $intent->custom_order_id,
            'amount_cents' => $intent->amount_cents,
            'state' => $intent->state,
        ];
    }
}
