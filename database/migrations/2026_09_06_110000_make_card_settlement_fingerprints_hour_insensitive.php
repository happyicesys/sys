<?php

use App\Models\CardSettlementRow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The dedupe fingerprint now ignores the transaction hour (see
 * CardSettlementRow::fingerprintFor). Rows ingested under the old
 * hour-sensitive key must be recomputed, or the next file uploaded would not
 * recognise lines already sitting in the table.
 *
 * Hashed in PHP, not SQL: MySQL 9 (the dev box) no longer ships SHA1().
 * Written back a chunk per statement so the ~95k rows in prod go in a
 * hundred round trips rather than a hundred thousand.
 *
 * Verified before writing this: over the 94,943 rows already in prod the new
 * key produces zero collisions between different transactions. Should one ever
 * appear (a re-run, another acquirer), the later row is salted exactly as
 * ingestion salts it — the unique index stays satisfied, and no row's status
 * or matched sale is touched either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recompute(fn (?string $time) => CardSettlementRow::minuteSecond($time));
    }

    public function down(): void
    {
        $this->recompute(fn (?string $time) => $time ?? '');
    }

    private function recompute(callable $timePart): void
    {
        $seen = [];

        DB::table('card_settlement_rows as w')
            ->join('card_settlement_reports as r', 'r.id', '=', 'w.card_settlement_report_id')
            ->select('w.id', 'w.row_no', 'w.terminal_id', 'w.transaction_date', 'w.transaction_time',
                'w.sequence_no', 'w.amount_cents', 'r.provider', 'r.id as report_id')
            ->orderBy('w.id')
            ->chunk(1000, function ($rows) use (&$seen, $timePart) {
                $cases = [];
                $bindings = [];
                $ids = [];

                foreach ($rows as $row) {
                    $fingerprint = sha1(implode('|', [
                        $row->provider,
                        $row->terminal_id,
                        substr((string) $row->transaction_date, 0, 10),
                        $row->sequence_no ?? '',
                        $row->amount_cents,
                        $timePart($row->transaction_time),
                    ]));

                    // Second sighting of one key: keep this row's copy distinct
                    // so the unique index accepts it, exactly as ingestion does.
                    if (isset($seen[$fingerprint])) {
                        $fingerprint = sha1($fingerprint.'|report:'.$row->report_id.'|row:'.$row->row_no);
                    }
                    $seen[$fingerprint] = true;

                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $row->id;
                    $bindings[] = $fingerprint;
                    $ids[] = $row->id;
                }

                DB::update(
                    'UPDATE card_settlement_rows SET fingerprint = CASE id '.implode(' ', $cases).' END'
                    .' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                    array_merge($bindings, $ids)
                );
            });
    }
};
