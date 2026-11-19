<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\RemoteCardTerminal;
use App\Services\CardTerminal\CardPaymentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * mark1's own check of every BOUND remote card terminal (Payrallel T05), every 30 s — Brian, 2026-11-19: "only
 * for those we have bound". The freezer already asks every 30 s while it is up; this keeps each terminal's online
 * / offline log going when it is not ({@see \App\Services\CardTerminal\TerminalStatusLog}).
 *
 * One read per terminal: `terminal/status` is a question to Payrallel's cloud, nothing appears on the T05 and a
 * payment in progress is not touched. A terminal answered in the last {@see FRESH_SECONDS} (the freezer asked) is
 * skipped, so a working freezer costs no extra call. Nothing to do where Payrallel is not set up (LSH, IDN).
 */
class WatchRemoteTerminalStatus extends Command
{
    protected $signature = 'card-terminals:watch-status';

    protected $description = 'Check every bound remote card terminal (T05) and keep its online / offline log';

    /** An answer younger than this needs no new call (runs are 30 s apart; the freezer polls every 30 s). */
    public const FRESH_SECONDS = 25;

    public function handle(CardPaymentService $payments): int
    {
        if (blank(config('payrallel.base_url'))) {
            return self::SUCCESS;
        }

        $terminals = RemoteCardTerminal::query()
            ->where('provider', RemoteCardTerminal::PROVIDER_PAYRALLEL)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('last_status_at')
                ->orWhere('last_status_at', '<', now()->subSeconds(self::FRESH_SECONDS)))
            ->get();

        foreach ($terminals as $terminal) {
            try {
                $status = $payments->statusOf($terminal);
                $this->line(sprintf('%s: %s%s', $terminal->label ?? $terminal->id,
                    $status->online ? 'online' : 'offline', $status->state ? " ({$status->state})" : ''));
            } catch (Throwable $e) {
                // One terminal never stops the others.
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
