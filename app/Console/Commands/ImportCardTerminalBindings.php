<?php

namespace App\Console\Commands;

use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Vend;
use Illuminate\Console\Command;

/**
 * Bulk-load card-terminal → machine bindings from a CSV
 * (headers: terminal_id, vend_code[, provider, bound_from, bound_until, remarks]).
 *
 * The seed for NETS was extracted from the ops sheet
 * "Nets Terminal TID-2025-2026.xlsx" (2026-08 tab):
 * database/data/card_terminal_bindings_nets_2026-08.csv
 *
 * Dry-run by default; --apply writes. Existing (provider, terminal_id,
 * vend) rows are left untouched, a same-terminal different-vend open binding
 * is reported as a conflict for manual review (change of machine = close the
 * old binding with bound_until, then import/create the new one).
 */
class ImportCardTerminalBindings extends Command
{
    protected $signature = 'card-settlement:import-bindings {path : CSV file} {--provider=nets} {--apply}';

    protected $description = 'Import card terminal → vend bindings from a CSV (dry-run unless --apply)';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle, null, ',', '"', '\\') ?: []);
        if (! in_array('terminal_id', $header) || ! in_array('vend_code', $header)) {
            $this->error('CSV must have terminal_id and vend_code columns.');

            return self::FAILURE;
        }

        $created = $skipped = $conflicts = $missing = 0;

        while (($line = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $row = array_combine($header, array_pad(array_map('trim', $line), count($header), ''));
            if ($row['terminal_id'] === '' || $row['vend_code'] === '') {
                continue;
            }

            $provider = $row['provider'] ?: $this->option('provider');
            $vend = Vend::withoutGlobalScopes()->where('code', $row['vend_code'])->first();
            if (! $vend) {
                $this->warn("SKIP {$row['terminal_id']}: no machine with code {$row['vend_code']}");
                $missing++;

                continue;
            }

            $isClosedRow = ($row['bound_until'] ?? '') !== '';

            if ($isClosedRow) {
                // History row: idempotence check is the exact same closed range.
                $exists = CardTerminalBinding::query()
                    ->where('provider', $provider)
                    ->where('terminal_id', $row['terminal_id'])
                    ->where('vend_id', $vend->id)
                    ->where('bound_until', $row['bound_until'])
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }
            } else {
                $existing = CardTerminalBinding::query()
                    ->where('provider', $provider)
                    ->where('terminal_id', $row['terminal_id'])
                    ->whereNull('bound_until')
                    ->first();

                if ($existing) {
                    if ((int) $existing->vend_id === (int) $vend->id) {
                        $skipped++;
                    } else {
                        $this->warn("CONFLICT {$row['terminal_id']}: open binding to vend #{$existing->vend_id}, sheet says {$row['vend_code']}");
                        $conflicts++;
                    }

                    continue;
                }
            }

            $this->line("BIND {$row['terminal_id']} -> {$row['vend_code']}".($row['bound_from'] ?? '' ? " from {$row['bound_from']}" : ''));
            if ($this->option('apply')) {
                $this->ensureTerminalUnit($row['terminal_id'], $vend);
                CardTerminalBinding::create([
                    'provider' => $provider,
                    'terminal_id' => $row['terminal_id'],
                    'vend_id' => $vend->id,
                    'bound_from' => $row['bound_from'] ?: null,
                    'bound_until' => $row['bound_until'] ?: null,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }
            $created++;
        }
        fclose($handle);

        $mode = $this->option('apply') ? 'APPLIED' : 'DRY-RUN (use --apply to write)';
        $this->info("{$mode}: {$created} new, {$skipped} already bound, {$conflicts} conflicts, {$missing} unknown machines.");

        return self::SUCCESS;
    }

    /**
     * Keep Data Management → Card Terminal in step with what we import.
     * Without a `card_terminal_units` row the terminal is invisible in the
     * machine Setting/Edit picker, so ops could never move it afterwards.
     * The company is taken from the machine it lands on, matching how the
     * 2026-09-05 backfill seeded the list.
     */
    private function ensureTerminalUnit(string $terminalId, Vend $vend): void
    {
        CardTerminalUnit::firstOrCreate(
            ['terminal_id' => $terminalId],
            ['card_terminal_id' => $vend->card_terminal_id]
        );
    }
}
