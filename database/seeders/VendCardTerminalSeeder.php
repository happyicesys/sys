<?php

namespace Database\Seeders;

use App\Models\CardTerminal;
use App\Models\Vend;
use Illuminate\Database\Seeder;

/**
 * Backfills `vends.card_terminal_id` from a hand-curated CSV (sourced from
 * the operations team's "card terminal as of 14May26" Excel — see
 * database/seeders/data/vend_card_terminal.csv).
 *
 * CSV format:
 *   id,card_terminal
 *   1149,Nayax
 *   2052,Nets
 *   ...
 *
 * Where `id` is the vend code (matches vends.code) and `card_terminal` is one
 * of {Nayax, Nets, Nets-Auresys, PAX, MLS} — or empty for vends without a
 * card terminal. Empty rows are skipped (vend's card_terminal_id stays
 * as-is).
 *
 * The CSV is generated from the "Card Terminal" column of the source Excel,
 * NOT the related "cashless_mfg" column (which holds the legacy VM-reported
 * codes CAS / NYX / 111 / etc.).
 *
 * Requires CardTerminalSeeder to have run first so the card_terminals table
 * is populated.
 *
 * Idempotent: safe to re-run; only writes when the resolved id differs from
 * the current value.
 */
class VendCardTerminalSeeder extends Seeder
{
    public function run()
    {
        $csvPath = database_path('seeders/data/vend_card_terminal.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("Source CSV not found: {$csvPath}");
            return;
        }

        // Build a name → id lookup for card_terminals so we can resolve each
        // CSV row to an FK without a per-row query.
        $terminalIdByName = CardTerminal::pluck('id', 'name')->all();

        if (empty($terminalIdByName)) {
            $this->command->error('card_terminals table is empty — run CardTerminalSeeder first.');
            return;
        }

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle); // id,card_terminal

        $updated = 0;
        $skippedEmpty = 0;
        $skippedNoMatch = 0;
        $skippedUnknownTerminal = 0;
        $unmatchedTerminals = [];

        while (($row = fgetcsv($handle)) !== false) {
            [$vendCode, $cardTerminalName] = array_pad($row, 2, '');
            $vendCode = trim((string) $vendCode);
            $cardTerminalName = trim((string) $cardTerminalName);

            if ($vendCode === '') {
                continue;
            }

            if ($cardTerminalName === '' || strcasecmp($cardTerminalName, 'NULL') === 0 || strcasecmp($cardTerminalName, 'N/A') === 0) {
                $skippedEmpty++;
                continue;
            }

            if (!isset($terminalIdByName[$cardTerminalName])) {
                $skippedUnknownTerminal++;
                $unmatchedTerminals[$cardTerminalName] = ($unmatchedTerminals[$cardTerminalName] ?? 0) + 1;
                continue;
            }

            $terminalId = $terminalIdByName[$cardTerminalName];

            $affected = Vend::where('code', $vendCode)
                ->where(function ($q) use ($terminalId) {
                    // only update rows that aren't already correct
                    $q->whereNull('card_terminal_id')
                        ->orWhere('card_terminal_id', '!=', $terminalId);
                })
                ->update(['card_terminal_id' => $terminalId]);

            if ($affected === 0) {
                // Either no such vend, or already-correct. Use a count query
                // to disambiguate.
                if (!Vend::where('code', $vendCode)->exists()) {
                    $skippedNoMatch++;
                }
            } else {
                $updated += $affected;
            }
        }

        fclose($handle);

        $this->command->info("VendCardTerminalSeeder done.");
        $this->command->info("  updated: {$updated}");
        $this->command->info("  skipped (no card terminal in CSV): {$skippedEmpty}");
        $this->command->info("  skipped (vend not found): {$skippedNoMatch}");
        $this->command->info("  skipped (unknown terminal name): {$skippedUnknownTerminal}");

        if (!empty($unmatchedTerminals)) {
            $this->command->warn("Unknown terminal names encountered: " . json_encode($unmatchedTerminals));
        }
    }
}
