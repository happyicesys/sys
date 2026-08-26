<?php

use App\Services\VendChannelDuplicateResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One vend_channels row per (vend_id, code) — enforced at the DB level.
 *
 * Duplicates used to be born from report-processing races and malformed
 * channel-code strings (vend 4753 / code 17, 2026-08-03: the stranded original
 * kept qty 22/34 forever and inflated the machine's stock totals). The index
 * makes that impossible; SyncVendChannels / VendTransactionService /
 * SyncVendChannelErrorLog create through firstOrCreate, so a racing insert
 * degrades into an update instead of an error.
 *
 * DEPLOY ORDER MATTERS. Queue workers keep running the OLD code until
 * `horizon:terminate`, which on a normal deploy fires AFTER `migrate` — so
 * during this migration a channel report can still mint a fresh duplicate
 * between the merge and the CREATE INDEX. That is not hypothetical: five of
 * the thirteen groups this migration cleans up were created on the morning of
 * 2026-08-26. Preferred sequence:
 *
 *   1. php artisan horizon:pause        (or terminate, and let it come back
 *                                        up on the NEW code)
 *   2. deploy the code                  (firstOrCreate + int-normalized codes
 *                                        stop new duplicates being minted)
 *   3. php artisan vend-channels:dedupe (dry-run — eyeball the plan)
 *   4. php artisan vend-channels:dedupe --apply
 *   5. php artisan migrate              (this file is then a no-op merge)
 *   6. php artisan horizon:continue
 *
 * Run out of order it still converges: the merge below is the same resolver
 * the command uses, and the index creation retries after re-merging whatever
 * a racing worker slipped in.
 */
return new class extends Migration
{
    /**
     * Merge-then-index attempts. Each retry re-runs the merge, so a duplicate
     * minted by a worker between the merge and the CREATE INDEX is absorbed
     * rather than failing the deploy.
     */
    private const ATTEMPTS = 3;

    public function up(): void
    {
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $this->mergeExistingDuplicates();

            try {
                Schema::table('vend_channels', function (Blueprint $table) {
                    $table->unique(['vend_id', 'code']);
                });

                return;
            } catch (QueryException $e) {
                // 1062 = duplicate entry: a report landed between the merge and
                // the DDL. Anything else (permissions, index already present,
                // lock timeout) is a real failure and must surface.
                if ($attempt === self::ATTEMPTS || ! $this->isDuplicateEntry($e)) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('vend_channels', function (Blueprint $table) {
            $table->dropUnique(['vend_id', 'code']);
        });
    }

    /**
     * MySQL refuses the unique index while duplicates exist, so pre-existing
     * violations (13 groups across 5 vends as of 2026-08-26) are merged first.
     *
     * The merge policy lives in one place — VendChannelDuplicateResolver, the
     * same class `vend-channels:dedupe` drives — rather than being copied into
     * this file as raw SQL, so the command and the migration can never drift.
     * The class_exists guard is the price of that coupling: if the service is
     * ever renamed, a historical replay (migrate:fresh on a clean database,
     * where there is nothing to merge anyway) must not hard-fail here.
     */
    private function mergeExistingDuplicates(): void
    {
        if (! class_exists(VendChannelDuplicateResolver::class)) {
            return;
        }

        (new VendChannelDuplicateResolver)->resolve(apply: true);
    }

    private function isDuplicateEntry(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
};
