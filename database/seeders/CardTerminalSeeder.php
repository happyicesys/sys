<?php

namespace Database\Seeders;

use App\Models\CardTerminal;
use App\Models\Vend;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the `card_terminals` table with the canonical list of card-terminal
 * manufacturer codes (CAS / NYX / PAX / 111 / MLS).
 *
 * Truncates both `card_terminals` and `cashless_terminals` first — the old
 * `cashless_providers` seed (Nayax / Castle / XVend / Auresys / Beeptech) was
 * never used in prod, and the `cashless_terminals` rows that depended on
 * those providers are being deprecated as part of the same migration.
 *
 * Idempotent: safe to re-run.
 */
class CardTerminalSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        // `cashless_terminals.cashless_provider_id` was the FK before the
        // rename — wipe child first to avoid orphan rows.
        DB::table('cashless_terminals')->truncate();
        DB::table('card_terminals')->truncate();

        Schema::enableForeignKeyConstraints();

        foreach (Vend::CARD_TERMINALS as $name) {
            CardTerminal::create(['name' => $name]);
        }
    }
}
