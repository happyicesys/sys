<?php

namespace App\Console\Commands\CardTerminal;

use App\Support\CardNumberMask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cuts the card numbers already stored down to first 2 + last 4 digits (CardNumberMask): the
 * Payrallel answers logged before that rule. Without --apply it only counts. Prints counts, never
 * a value. Rows go through DB::table, so no `updated_at`, event or audit row moves.
 *
 *   php artisan card-payments:mask-card-numbers [--apply]
 */
class MaskStoredCardNumbers extends Command
{
    private const COLUMNS = [
        ['card_payment_events', 'detail'],
        ['card_payment_intents', 'last_response'],
        ['card_refunds', 'response'],
    ];

    protected $signature = 'card-payments:mask-card-numbers
        {--apply : Rewrite the rows and log files; without it nothing changes}';

    protected $description = 'Cut stored card numbers down to the first 2 and last 4 digits';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        foreach (self::COLUMNS as [$table, $column]) {
            $changed = 0;
            DB::table($table)->select('id', $column)->whereNotNull($column)
                ->chunkById(500, function ($rows) use ($table, $column, $apply, &$changed) {
                    foreach ($rows as $row) {
                        $masked = CardNumberMask::text((string) $row->{$column});
                        if ($masked === (string) $row->{$column}) {
                            continue;
                        }
                        $changed++;
                        if ($apply) {
                            DB::table($table)->where('id', $row->id)->update([$column => $masked]);
                        }
                    }
                });
            $this->line(sprintf('%s.%s: %d row(s) %s', $table, $column, $changed, $apply ? 'masked' : 'to mask'));
        }

        // Kept whole, in the same file, so a worker holding the day's log open keeps appending to it.
        $files = 0;
        foreach (glob(storage_path('logs/payrallel-*.log')) ?: [] as $path) {
            $text = (string) file_get_contents($path);
            $masked = CardNumberMask::text($text);
            if ($masked === $text) {
                continue;
            }
            $files++;
            if ($apply) {
                file_put_contents($path, $masked, LOCK_EX);
            }
        }
        $this->line(sprintf('payrallel log files: %d %s', $files, $apply ? 'masked' : 'to mask'));

        return self::SUCCESS;
    }
}
