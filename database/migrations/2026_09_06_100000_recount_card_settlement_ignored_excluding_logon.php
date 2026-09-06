<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ignored now means "a user dismissed this query", not "the matcher skipped a
 * Logon line". Stored counts were written by the old rule, so recount the
 * reports already ingested — nothing recomputes them until a report is
 * re-matched, and most never are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recount("txn_type = 'Purchase'");
    }

    public function down(): void
    {
        $this->recount('1 = 1');
    }

    private function recount(string $predicate): void
    {
        DB::statement("
            UPDATE card_settlement_reports r
            SET r.ignored_count = (
                SELECT COUNT(*)
                FROM card_settlement_rows w
                WHERE w.card_settlement_report_id = r.id
                  AND w.status = 4
                  AND {$predicate}
            )
        ");
    }
};
