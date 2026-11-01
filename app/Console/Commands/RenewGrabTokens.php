<?php

namespace App\Console\Commands;

use App\Services\Grab\GrabTokenKeeper;
use Illuminate\Console\Command;

/**
 * Renews every live Grab account's token within a day of its expiry (hourly). Before this
 * nothing renewed a token on a schedule, so a quiet or paused account lapsed after 7 days
 * and Grab refused all its calls (DCVIC from 2026-10-29, LEA on 2026-10-31).
 */
class RenewGrabTokens extends Command
{
    protected $signature = 'grab:renew-tokens {--force : renew every live account now, whatever its expiry}';

    protected $description = 'Renew Grab access tokens before they expire';

    public function handle(GrabTokenKeeper $keeper): int
    {
        $accounts = $keeper->accounts()->keyBy('id');
        $results = $keeper->renewDue((bool) $this->option('force'));

        $this->table(['Account', 'Operator', 'Result', 'Expires'], collect($results)->map(fn ($result, $id) => [
            $id,
            $accounts[$id]?->operator?->code,
            $result,
            $keeper->expiresAt($accounts[$id]->fresh('externalOauthToken'))?->toDateTimeString() ?? '—',
        ])->values()->all());

        return in_array('failed', $results, true) ? self::FAILURE : self::SUCCESS;
    }
}
