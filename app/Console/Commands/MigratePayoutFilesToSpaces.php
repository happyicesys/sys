<?php

namespace App\Console\Commands;

use App\Support\PayoutFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time (rerunnable) move of the bank payout files written before
 * 2026-10-09 from the droplet's local disk to the payout disk (private DO
 * Spaces, App\Support\PayoutFiles). Every file under the payout folders is
 * copied PRIVATE and verified byte-for-byte; local copies are deleted only on
 * an explicit --delete-local pass, and only when the remote copy matches.
 * Downloads read Spaces first and fall back to local, so this is safe to run
 * at any time after the deploy.
 *
 *   php artisan payout-files:migrate-to-spaces --dry-run
 *   php artisan payout-files:migrate-to-spaces
 *   php artisan payout-files:migrate-to-spaces --delete-local
 */
class MigratePayoutFilesToSpaces extends Command
{
    public const DIRS = ['refund-payouts', 'commission-payouts'];

    protected $signature = 'payout-files:migrate-to-spaces {--dry-run} {--delete-local}';

    protected $description = 'Move refund + commission payout files from local disk to private DO Spaces (verified; optionally delete local copies)';

    public function handle(): int
    {
        $target = PayoutFiles::disk();
        if ($target === 'local') {
            $this->error('The payout disk resolves to "local" — set the DO Spaces key/secret (and PAYOUT_FILES_DISK) first.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $deleteLocal = (bool) $this->option('delete-local');
        $local = Storage::disk('local');
        $remote = Storage::disk($target);
        $copied = $skipped = $deleted = $failed = 0;

        $files = collect(self::DIRS)->flatMap(fn ($dir) => $local->allFiles($dir))->sort()->values();

        foreach ($files as $path) {
            $bytes = $local->get($path);

            if (! $remote->exists($path)) {
                if ($dry) {
                    $this->line("would copy: {$path}");
                    $copied++;

                    continue;
                }
                if (! $remote->put($path, $bytes, ['visibility' => 'private'])) {
                    $this->error("UPLOAD FAIL: {$path}");
                    $failed++;

                    continue;
                }
                $copied++;
            } else {
                $skipped++;
            }

            if ($dry || ! $remote->exists($path)) {
                continue;
            }

            if ($remote->get($path) !== $bytes) {
                $this->error("CONTENT MISMATCH (kept local): {$path}");
                $failed++;

                continue;
            }

            if ($deleteLocal) {
                $local->delete($path);
                $deleted++;
            }
        }

        // Every path the payout tables name must now be readable somewhere.
        $missing = $this->referencedPaths()->reject(fn ($p) => PayoutFiles::locate($p))->values();
        foreach ($missing as $p) {
            $this->warn("REFERENCED BUT MISSING everywhere: {$p}");
        }

        $this->info(($dry ? '[DRY RUN] ' : '')."files={$files->count()} copied={$copied} already-there={$skipped} local-deleted={$deleted} failed={$failed} referenced-missing={$missing->count()}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function referencedPaths()
    {
        return collect()
            ->merge(DB::table('refund_payout_batches')->whereNotNull('csv_path')->pluck('csv_path'))
            ->merge(DB::table('refund_settlement_exports')->whereNotNull('file_path')->pluck('file_path'))
            ->merge(DB::table('commission_settlements')->whereNotNull('csv_path')->pluck('csv_path'))
            ->merge(DB::table('commission_settlement_exports')->whereNotNull('file_path')->pluck('file_path'))
            ->filter()
            ->unique()
            ->values();
    }
}
