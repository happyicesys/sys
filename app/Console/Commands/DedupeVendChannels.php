<?php

namespace App\Console\Commands;

use App\Services\VendChannelDuplicateResolver;
use Illuminate\Console\Command;

/**
 * Merges duplicate vend_channels rows (same vend_id + code) so the unique
 * (vend_id, code) index can exist and machine stock/capacity totals stop
 * counting frozen ghost rows. The migration adding the index runs the same
 * resolver itself; this command is the pre-flight inspection tool (and the
 * manual fallback if a duplicate ever appears with the index absent).
 *
 * DRY-RUN BY DEFAULT. Pass --apply to write. See VendChannelDuplicateResolver
 * for the merge policy (survivor = oldest id, state = most recent row).
 */
class DedupeVendChannels extends Command
{
    protected $signature = 'vend-channels:dedupe
        {--apply : actually merge; without it the plan is only printed}';

    protected $description = 'Merge duplicate vend_channels rows sharing one (vend_id, code) pair (dry-run unless --apply)';

    public function handle(VendChannelDuplicateResolver $resolver): int
    {
        $apply = (bool) $this->option('apply');
        $results = $resolver->resolve($apply);

        if ($results === []) {
            $this->info('No duplicate vend_channels rows found.');

            return self::SUCCESS;
        }

        $this->table(
            ['vend_id', 'code', 'survivor id', 'state from', 'deleted ids', 'repointed rows'],
            collect($results)->map(fn (array $row) => [
                $row['vend_id'],
                $row['code'],
                $row['survivor'],
                $row['donor'],
                implode(', ', $row['deleted']),
                collect($row['repointed'])->map(fn ($n, $t) => "$t: $n")->implode(', ') ?: '—',
            ])
        );

        $this->info(sprintf(
            '%s %d duplicate group(s), removing %d row(s).',
            $apply ? 'Merged' : 'Would merge',
            count($results),
            collect($results)->sum(fn ($row) => count($row['deleted'])),
        ));

        if (! $apply) {
            $this->comment('Dry-run only. Re-run with --apply to merge.');
        }

        return self::SUCCESS;
    }
}
