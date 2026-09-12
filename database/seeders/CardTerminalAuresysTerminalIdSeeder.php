<?php

namespace Database\Seeders;

use App\Models\CardTerminalUnit;
use Illuminate\Database\Seeder;

/**
 * One-off backfill of `card_terminal_units.auresys_terminal_id` out of the
 * remarks ops had been typing it into.
 *
 * The convention in the field is "EZTID: 25670011" — the label, then the
 * numeric Auresys terminal ID. Every one of the 25 rows carrying a remark on
 * prod (2026-09-12) is exactly that, but the regex is written loosely on
 * purpose: label case-insensitive, an optional colon, any run of spaces, so a
 * row typed "eztid 25670011" is picked up too.
 *
 *   php artisan db:seed --class=CardTerminalAuresysTerminalIdSeeder
 *
 * Idempotent and non-destructive:
 *  - a unit that already has an auresys_terminal_id is left alone, so re-running
 *    never overwrites a value someone has since corrected on the page;
 *  - the remark itself is KEPT. It is what ops recognise, and dropping it would
 *    destroy the only copy if this parse ever turned out to be wrong.
 */
class CardTerminalAuresysTerminalIdSeeder extends Seeder
{
    /** "EZTID: 25670011", "eztid 25670011", "EZ TID:25670011". */
    private const PATTERN = '/\bEZ\s*TID\s*:?\s*(\d+)/i';

    public function run(): void
    {
        $filled = 0;
        $skipped = 0;

        CardTerminalUnit::query()
            ->whereNull('auresys_terminal_id')
            ->whereNotNull('remarks')
            ->where('remarks', '<>', '')
            ->each(function (CardTerminalUnit $unit) use (&$filled, &$skipped) {
                if (! preg_match(self::PATTERN, (string) $unit->remarks, $matches)) {
                    $skipped++;

                    return;
                }

                // updated_at deliberately left alone: this is a backfill of
                // what the row already said, not an edit anybody made.
                $unit->timestamps = false;
                $unit->forceFill(['auresys_terminal_id' => $matches[1]])->saveQuietly();
                $filled++;
            });

        $this->command?->info("Auresys terminal IDs filled: {$filled}; remarks with no EZTID: {$skipped}.");
    }
}
