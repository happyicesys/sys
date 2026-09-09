<?php

namespace App\Console\Commands;

use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Seed `card_terminal_units.batch` / `is_will_auto_refund` from the partner's
 * per-TID workbook (repo copy: database/data/card_terminal_auto_refund_seed_2026-09-08.csv).
 *
 * The sheet is AUTHORITATIVE for the flag (Brian, 2026-09-09): a terminal it
 * says voids a failed sale is ticked "NA in NETS" by the reconciler when no
 * line arrives; one it says does not, or does not list, is never ticked. A
 * flag an admin set by hand (source `manual`) is kept; the batch and the
 * sheet's statistics are still refreshed.
 *
 *   php artisan card-settlement:import-terminal-flags database/data/card_terminal_auto_refund_seed_2026-09-08.csv
 *   php artisan card-settlement:import-terminal-flags <csv> --apply --create-missing
 *
 * CSV columns: terminal_id, batch, is_will_auto_refund (1 / 0 / blank = unknown),
 * flag_basis, auto_refund_events, unrefunded_events, batch_rate, window_from,
 * window_to, sheet_status_v3.
 */
class ImportCardTerminalAutoRefundFlags extends Command
{
    protected $signature = 'card-settlement:import-terminal-flags
        {csv : Path to the seed CSV}
        {--apply : Write (default is a dry run)}
        {--create-missing : Create a card_terminal_units row for a TID the sheet lists but mark1 does not have (company from the batch name)}';

    protected $description = 'Seed per-terminal batch and will-auto-refund flags from the partner workbook CSV (dry-run by default)';

    public function handle(): int
    {
        $path = $this->argument('csv');
        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $rows = $this->readCsv($path);
        $units = CardTerminalUnit::query()->whereIn('terminal_id', array_column($rows, 'terminal_id'))->get()->keyBy('terminal_id');
        $companies = CardTerminal::query()->get()->keyBy(fn ($c) => strtolower($c->name));

        $stats = ['updated' => 0, 'manual_kept' => 0, 'created' => 0, 'missing' => 0, 'yes' => 0, 'no' => 0, 'unknown' => 0];
        $seededAt = Carbon::now()->toDateTimeString();

        foreach ($rows as $row) {
            $tid = trim((string) $row['terminal_id']);
            if ($tid === '') {
                continue;
            }
            $flag = match (trim((string) ($row['is_will_auto_refund'] ?? ''))) {
                '1' => 1,
                '0' => 0,
                default => null,
            };
            $stats[$flag === 1 ? 'yes' : ($flag === 0 ? 'no' : 'unknown')]++;

            $unit = $units->get($tid);
            if (! $unit) {
                if (! $this->option('create-missing')) {
                    $stats['missing']++;
                    $this->line("  missing in mark1: {$tid} ({$row['batch']})");

                    continue;
                }
                $company = str_starts_with(strtolower((string) $row['batch']), 'auresys')
                    ? $companies->get('nets-auresys')
                    : $companies->get('nets');
                $unit = new CardTerminalUnit(['terminal_id' => $tid, 'card_terminal_id' => $company?->id]);
                $stats['created']++;
            }

            $attrs = [
                'batch' => $row['batch'] ?: null,
                'auto_refund_stats_json' => array_merge((array) $unit->auto_refund_stats_json, [
                    'seed_flag' => $flag, // kept so a manual override can be set back to "auto"
                    'seed' => [
                        'basis' => $row['flag_basis'] ?? null,
                        'auto_refund_events' => (int) ($row['auto_refund_events'] ?? 0),
                        'unrefunded_events' => (int) ($row['unrefunded_events'] ?? 0),
                        'batch_rate' => $row['batch_rate'] !== '' ? (float) $row['batch_rate'] : null,
                        'window_from' => $row['window_from'] ?? null,
                        'window_to' => $row['window_to'] ?? null,
                        'sheet_status' => $row['sheet_status_v3'] ?? null,
                        'seeded_at' => $seededAt,
                    ],
                ]),
            ];
            if ($unit->auto_refund_flag_source === CardTerminalUnit::FLAG_SOURCE_MANUAL) {
                $stats['manual_kept']++;
            } else {
                $attrs['is_will_auto_refund'] = $flag;
                $attrs['auto_refund_flag_source'] = CardTerminalUnit::FLAG_SOURCE_SEED;
            }

            if ($apply) {
                $unit->fill($attrs)->save();
            }
            $stats['updated']++;
        }

        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info(($apply ? 'Applied.' : 'Dry run — add --apply to write.'));

        return self::SUCCESS;
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = array_map('trim', fgetcsv($fh));
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) < count($header)) {
                $line = array_pad($line, count($header), '');
            }
            $rows[] = array_combine($header, array_map('trim', array_slice($line, 0, count($header))));
        }
        fclose($fh);

        return $rows;
    }
}
