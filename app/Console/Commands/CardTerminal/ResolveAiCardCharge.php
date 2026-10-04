<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\CardPaymentIntent;
use App\Services\CardTerminal\CardPaymentService;
use DomainException;
use Illuminate\Console\Command;

/**
 * A T05 charge decided by the AI whose outcome mark1 could not learn (timeout, 5xx, a run that
 * died mid-call) is never resent: it may already have been taken. Check the order in
 * Payrallel's portal, then say what happened:
 *
 *   php artisan card-payments:resolve-ai-charge SFTEST261003225442 --charged
 *   php artisan card-payments:resolve-ai-charge SFTEST261003225442 --not-charged
 *
 * Without an option it shows the flagged charge and changes nothing.
 */
class ResolveAiCardCharge extends Command
{
    protected $signature = 'card-payments:resolve-ai-charge
        {reference : the card attempt reference (or the full custom order id)}
        {--charged : Payrallel shows the flagged charge was taken}
        {--not-charged : Payrallel shows it was not taken (it is sent again)}';

    protected $description = 'Resolve an AI-decided T05 charge whose outcome is unknown';

    public function handle(CardPaymentService $payments): int
    {
        $ref = (string) $this->argument('reference');
        $intents = CardPaymentIntent::query()->where('reference', $ref)->orWhere('custom_order_id', $ref)->get();
        if ($intents->count() !== 1) {
            $this->error($intents->isEmpty() ? 'No card attempt with that reference.' : 'Several attempts match; give the custom order id.');

            return self::FAILURE;
        }
        $intent = $intents->first();
        $decision = (array) $intent->ai_decision;
        $this->line(sprintf('%s  state=%s  charges=%s  paid=%s  flagged=%s',
            $intent->custom_order_id, $intent->state,
            json_encode($decision['charges'] ?? []), json_encode($decision['paid'] ?? []),
            isset($decision['uncertain']) ? $decision['uncertain'].'c' : 'none'));

        $charged = (bool) $this->option('charged');
        if ($charged === (bool) $this->option('not-charged')) {
            if ($charged) {
                $this->error('Give --charged or --not-charged, not both.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        try {
            $intent = $payments->resolveUncertainAiCharge($intent, $charged);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Now {$intent->state}, captured {$intent->captured_cents}c, not charged yet ".($intent->owed_cents ?? 0).'c.');

        return self::SUCCESS;
    }
}
