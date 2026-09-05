<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master list of physical card terminal units — "Card Terminal" in Data
 * Management (the tab renamed from the old "Card Terminals", which is now
 * "Card Terminal Company" and still lives in `card_terminals`).
 *
 * Naming, because the two are easy to confuse:
 *   card_terminals       = the COMPANY (Nayax, Nets, Nets-Auresys, PAX, MLS, HID)
 *   card_terminal_units  = one physical terminal: its acquirer TID + its company
 *   card_terminal_bindings = that terminal sat on that machine over a date range
 *
 * This table is deliberately NOT referenced by card_terminal_bindings: the
 * settlement matcher keys on (provider, terminal_id) and 312 live binding rows
 * already do so. The unit list feeds the terminal picker on the machine
 * Setting/Edit page, which is now the only place a binding is created.
 *
 * `terminal_id` is globally unique (not per-company): an acquirer TID is the
 * matching key for settlement, so the same string under two companies would
 * make CardSettlementMatcher pick a machine arbitrarily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_terminal_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_terminal_id')->nullable();
            $table->string('terminal_id', 64);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique('terminal_id');
            $table->index('card_terminal_id');
        });

        $this->backfillFromBindings();
    }

    public function down(): void
    {
        Schema::dropIfExists('card_terminal_units');
    }

    /**
     * Seed the list from the terminals already bound to machines, so the
     * Setting/Edit picker is populated on day one rather than making ops
     * retype 309 NETS TIDs.
     *
     * Company comes from the machine the terminal is currently (or was last)
     * bound to — vends.card_terminal_id. Where the machine carries no company
     * we fall back to the company whose name matches the binding's provider
     * ("nets" → "Nets"), which covers the 5 rows measured on prod 2026-09-05.
     */
    private function backfillFromBindings(): void
    {
        if (! Schema::hasTable('card_terminal_bindings')) {
            return;
        }

        $companyByName = DB::table('card_terminals')
            ->get(['id', 'name'])
            ->keyBy(fn ($row) => strtolower($row->name))
            ->map(fn ($row) => $row->id);

        // Latest binding wins, so a terminal that has moved takes the company
        // of the machine it sits on now.
        $bindings = DB::table('card_terminal_bindings as b')
            ->leftJoin('vends as v', 'v.id', '=', 'b.vend_id')
            ->orderBy('b.id')
            ->get(['b.terminal_id', 'b.provider', 'v.card_terminal_id']);

        $units = [];
        $now = now();

        foreach ($bindings as $binding) {
            $terminalId = trim((string) $binding->terminal_id);
            if ($terminalId === '') {
                continue;
            }

            $companyId = $binding->card_terminal_id
                ?: ($companyByName[strtolower((string) $binding->provider)] ?? null);

            $units[$terminalId] = [
                'card_terminal_id' => $companyId,
                'terminal_id' => $terminalId,
                'remarks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk(array_values($units), 200) as $chunk) {
            DB::table('card_terminal_units')->insert($chunk);
        }
    }
};
