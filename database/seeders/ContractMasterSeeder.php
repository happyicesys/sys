<?php

namespace Database\Seeders;

use App\Jobs\ProcessCustomerSummaryMonth;
use App\Models\Customer;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Seeds placement-contract details for the full customer master.
 *
 * Data source:
 *   database/seeders/data/contract_master.csv
 *
 * Why this exists (and supersedes ZooActiveSgContractSeeder):
 *   The earlier seeder embedded ~30 rows inline and synchronously recomputed
 *   customer_period_summaries for every affected month. With 400+ rows
 *   spanning 2018→2026 (~95 months), that approach would lock the DB for a
 *   long stretch.
 *
 *   This version is built lean:
 *     1. Data lives in a sibling CSV — swap data without touching code.
 *     2. ONE bulk SELECT resolves all machine_ids → customer_ids. Missing
 *        rows are listed at the end, not failed mid-run.
 *     3. ONE transaction does the writes. Per-row work is just a couple of
 *        small queries; even 400 rows finishes in seconds.
 *     4. The summary recompute is DISPATCHED, not run inline — one
 *        ProcessCustomerSummaryMonth job per affected month onto the `low`
 *        queue. The low queue is capped at 4 workers in Horizon, so the
 *        realtime/default queue stays clean and the recompute trickles in
 *        the background.
 *
 *   php artisan db:seed --class=ContractMasterSeeder
 *
 * Toggle the dispatch off if you'd rather wait for tonight's 01:00 cron:
 *   CONTRACT_SEEDER_SKIP_SUMMARY=1 php artisan db:seed --class=ContractMasterSeeder
 *
 * Idempotent — safe to re-run. Per-customer `seeder`-sourced logs are
 * delete-and-reinserted at the same effective_from; non-seeder active logs
 * are closed at contract_from (only if they started strictly before).
 *
 * Field semantics (mirrors CustomerSummaryAggregator::computeLocationFeeCents):
 *   F      value/value2/ps_term = null          → 0 fee
 *   S      value = subsidy $                    → -value × 100 (income)
 *   R      value = rental $                     → +value × 100
 *   U      value = utility $                    → +value × 100
 *   PS     value = PS%, ps_term = term%         → sales × term% × value%
 *   PS+U   + value2 = utility $                 → PS amount + value2
 *   PSORU  + value2 = utility $                 → max(PS amount, value2)
 *
 * Notice Period — stored as one of Customer::NOTICE_PERIOD_OPTIONS
 * ('1 wk', '2 wk', '3 wk', '1 mth', '1.5 mth', '2 mth', '3 mth', 'NO need',
 * 'Cant ETerm'). Anything in the CSV that doesn't match is set to null on
 * the customer and flagged in the unresolved list.
 *
 * Location Grading — three independent A/B/C selections stored on `customers`
 * (NOT mirrored to `customer_contract_logs`, since grading is a placement
 * attribute, not contract-versioned). See Customer::LOCATION_GRADING_CATEGORIES.
 */
class ContractMasterSeeder extends Seeder
{
    private const CSV_RELATIVE = '/data/contract_master.csv';

    public function run(): void
    {
        $this->guardSchema();

        $rows = $this->loadCsv(__DIR__ . self::CSV_RELATIVE);
        $this->command?->info(sprintf('ContractMasterSeeder: loaded %d rows from CSV', count($rows)));

        // 1. Resolve every machine_id → customer_id in one query.
        $machineIds = array_values(array_unique(array_map(
            fn ($r) => (string) $r['machine_id'],
            $rows
        )));

        // pluck on duplicate keys keeps the last assignment, so ORDER BY id ASC
        // means the highest-id (most recent) vend wins for a given code.
        $vendMap = Vend::query()
            ->withoutGlobalScopes()
            ->whereIn('code', $machineIds)
            ->whereNotNull('customer_id')
            ->orderBy('id', 'asc')
            ->pluck('customer_id', 'code')
            ->toArray();

        // 2. Bucket: resolved vs missing.
        $missing = [];
        $writes = [];
        $earliest = null;

        foreach ($rows as $row) {
            $machineId = (string) $row['machine_id'];
            $customerId = $vendMap[$machineId] ?? null;
            if (!$customerId) {
                $missing[] = $machineId;
                continue;
            }
            if ($row['contract_from'] === '') {
                $missing[] = $machineId . ' (no contract_from)';
                continue;
            }

            $contractFrom = Carbon::parse($row['contract_from'])->startOfDay();

            // Notice period: validate against the enum on the model. Anything
            // that doesn't match becomes null (logged in the unresolved list so
            // it's not silent).
            $noticeRaw = $row['notice_period'] ?? '';
            $notice = null;
            if ($noticeRaw !== '') {
                if (in_array($noticeRaw, Customer::NOTICE_PERIOD_OPTIONS, true)) {
                    $notice = $noticeRaw;
                } else {
                    $missing[] = $machineId . " (unknown notice_period: '{$noticeRaw}')";
                    // Still write the customer row, just with null notice_period.
                }
            }

            $writes[] = [
                'customer_id'    => (int) $customerId,
                'machine_id'     => $machineId,
                'type'           => $row['contract_type'],
                'value'          => $this->nullableFloat($row['value']),
                'value2'         => $this->nullableFloat($row['value2']),
                'ps_term'        => $this->nullableFloat($row['ps_term']),
                'contract_from'  => $contractFrom,
                'contract_until' => $row['contract_until'] !== '' ? $row['contract_until'] : null,
                'auto_renewal'   => $row['auto_renewal'] === '1',
                'notice_period'  => $notice,
                'remarks'        => $row['remarks'] !== '' ? $row['remarks'] : null,
                // Location grading lives on `customers` only — NOT on
                // customer_contract_logs (it's a placement attribute, not a
                // contract-versioned one). Stored as A/B/C/null.
                'grade_placement'   => $this->gradeOrNull($row['location_grading_placement'] ?? ''),
                'grade_access'      => $this->gradeOrNull($row['location_grading_access'] ?? ''),
                'grade_flexibility' => $this->gradeOrNull($row['location_grading_flexibility'] ?? ''),
            ];
            if ($earliest === null || $contractFrom->lt($earliest)) {
                $earliest = $contractFrom->copy();
            }
        }

        if (empty($writes)) {
            $this->command?->warn('No matched customers — nothing to write.');
            $this->reportMissing($missing);
            return;
        }

        $this->command?->info(sprintf(
            ' - resolved: %d   unresolved: %d',
            count($writes),
            count($missing)
        ));

        // 3. All writes inside one transaction. Per-row is ~3 small queries.
        $now = now();
        $logRows = [];
        DB::transaction(function () use ($writes, $now, &$logRows) {
            foreach ($writes as $w) {
                $this->writeCustomer($w, $now);
                $this->prepareContractLog($w, $now, $logRows);
            }
            // Bulk insert all new log rows in chunks (one INSERT statement
            // per chunk vs 400 individual inserts).
            foreach (array_chunk($logRows, 200) as $chunk) {
                DB::table('customer_contract_logs')->insert($chunk);
            }
        });

        $this->command?->info(sprintf(
            ' - %d customers updated, %d contract_logs inserted',
            count($writes),
            count($logRows)
        ));

        // 4. Recompute customer_period_summaries via the low queue.
        if (env('CONTRACT_SEEDER_SKIP_SUMMARY')) {
            $this->command?->info('CONTRACT_SEEDER_SKIP_SUMMARY=1 — skipping summary dispatch.');
            $this->command?->info(sprintf(
                'To recompute later: php artisan customer-summary:compute --from=%s --to=%s',
                $earliest->format('Y-m'),
                Carbon::today()->format('Y-m')
            ));
        } else {
            $this->dispatchSummaryRecompute($earliest);
        }

        $this->reportMissing($missing);
    }

    /**
     * Fails fast if the customer schema hasn't been migrated yet.
     */
    private function guardSchema(): void
    {
        $required = [
            'contract_from',
            'contract_remarks',
            'contract_detail_updated_at',
            // Added in 2026_05_12: location grading
            'location_grading_placement',
            'location_grading_access',
            'location_grading_flexibility',
        ];
        foreach ($required as $col) {
            if (!Schema::hasColumn('customers', $col)) {
                throw new RuntimeException(sprintf(
                    'customers.%s missing — run `php artisan migrate` before this seeder.',
                    $col
                ));
            }
        }
        // Notice period must be the new string column (migration
        // 2026_05_13_000000_change_contract_notice_period_to_string). Detect
        // pre-migrate state by sniffing the column type.
        $type = Schema::getColumnType('customers', 'contract_notice_period');
        if (!in_array($type, ['string', 'varchar', 'text', 'char'], true)) {
            throw new RuntimeException(sprintf(
                'customers.contract_notice_period is still %s — run `php artisan migrate` so it becomes a string column.',
                $type
            ));
        }
        if (!Schema::hasTable('customer_contract_logs')) {
            throw new RuntimeException('customer_contract_logs table missing — run `php artisan migrate` first.');
        }
    }

    private function loadCsv(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("CSV not found at {$path}");
        }
        $fh = fopen($path, 'r');
        $headers = fgetcsv($fh);
        if (!$headers) {
            fclose($fh);
            throw new RuntimeException("CSV at {$path} has no header row");
        }
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            if (count($line) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, $line);
        }
        fclose($fh);
        return $rows;
    }

    private function nullableFloat(string $v): ?float
    {
        return $v === '' ? null : (float) $v;
    }

    /**
     * Returns 'A' | 'B' | 'C' | null for the three location_grading_* columns.
     * Defensive — the CSV builder already normalises, but this keeps the seeder
     * safe against future hand-edits.
     */
    private function gradeOrNull(string $v): ?string
    {
        $v = strtoupper(trim($v));
        return in_array($v, ['A', 'B', 'C'], true) ? $v : null;
    }

    private function writeCustomer(array $w, $now): void
    {
        DB::table('customers')
            ->where('id', $w['customer_id'])
            ->update([
                'contract_commission_type'     => $w['type'],
                'contract_commission_value'    => $w['value'],
                'contract_commission_value2'   => $w['value2'],
                'contract_ps_term'             => $w['ps_term'],
                'contract_from'                => $w['contract_from']->toDateString(),
                'contract_until'               => $w['contract_until'],
                'contract_auto_renewal'        => $w['auto_renewal'],
                'contract_notice_period'       => $w['notice_period'],
                'contract_remarks'             => $w['remarks'],
                'contract_detail_updated_at'   => $now,
                'updated_at'                   => $now,
                // Location grading — placement attributes, not contract block.
                'location_grading_placement'   => $w['grade_placement'],
                'location_grading_access'      => $w['grade_access'],
                'location_grading_flexibility' => $w['grade_flexibility'],
            ]);
    }

    /**
     * Closes any pre-existing active non-seeder log, wipes prior seeder rows
     * at the same effective_from (for idempotency), and queues a new row to
     * be bulk-inserted at the end of the transaction.
     */
    private function prepareContractLog(array $w, $now, array &$logRows): void
    {
        $customerId   = $w['customer_id'];
        $contractFrom = $w['contract_from'];

        DB::table('customer_contract_logs')
            ->where('customer_id', $customerId)
            ->whereNull('effective_to')
            ->where('source', '!=', 'seeder')
            ->where('effective_from', '<', $contractFrom)
            ->update(['effective_to' => $contractFrom, 'updated_at' => $now]);

        DB::table('customer_contract_logs')
            ->where('customer_id', $customerId)
            ->where('source', 'seeder')
            ->where('effective_from', $contractFrom)
            ->delete();

        $logRows[] = [
            'customer_id'                => $customerId,
            'effective_from'             => $contractFrom,
            'effective_to'               => null,
            'contract_commission_type'   => $w['type'],
            'contract_commission_value'  => $w['value'],
            'contract_commission_value2' => $w['value2'],
            'contract_ps_term'           => $w['ps_term'],
            'contract_from'              => $contractFrom->toDateString(),
            'contract_until'             => $w['contract_until'],
            'contract_auto_renewal'      => $w['auto_renewal'],
            'contract_notice_period'     => $w['notice_period'],
            'contract_remarks'           => $w['remarks'],
            'source'                     => 'seeder',
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ];
    }

    private function dispatchSummaryRecompute(Carbon $earliest): void
    {
        $rangeStart = $earliest->copy()->startOfMonth();
        $rangeEnd   = Carbon::today()->startOfMonth();
        $asOf       = Carbon::today()->subDay();

        // Floor to gp_metrics earliest date — nothing useful to aggregate
        // before transactions exist.
        $gpMin = DB::table('gp_metrics')->min('txn_date');
        if ($gpMin) {
            $gpStart = Carbon::parse($gpMin)->startOfMonth();
            if ($gpStart->gt($rangeStart)) {
                $rangeStart = $gpStart;
            }
        }

        $count = 0;
        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            ProcessCustomerSummaryMonth::dispatch(
                $cursor->copy()->startOfMonth()->toDateString(),
                $asOf->toDateString()
            )->onQueue('low');
            $cursor->addMonthNoOverflow();
            $count++;
        }

        $this->command?->info(sprintf(
            'Dispatched %d ProcessCustomerSummaryMonth jobs onto the `low` queue (%s → %s).',
            $count,
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m')
        ));
    }

    private function reportMissing(array $missing): void
    {
        if (empty($missing)) {
            return;
        }
        $this->command?->warn(sprintf(
            'Could not match %d machine_id(s) — vend not found, unbound, or contract_from blank:',
            count($missing)
        ));
        foreach (array_chunk($missing, 20) as $chunk) {
            $this->command?->warn('  ' . implode(', ', $chunk));
        }
    }
}
