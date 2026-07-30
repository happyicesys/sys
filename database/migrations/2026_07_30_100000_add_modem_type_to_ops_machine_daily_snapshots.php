<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the machine's Data Management "Modem Model" (vends.modem_type_id) to the
 * per-machine snapshot, so Ops Performance can categorise the fleet by modem
 * model in the Machines' Status & Component section.
 *
 * Like the other component columns this is pure machine STATE — `vends` keeps no
 * history — so the label is denormalised onto each row (mirroring
 * card_terminal_id / card_terminal_name) and no join is needed at read time.
 * modem_type_name stores the SHORT label (modem_types.alias, falling back to
 * .name) because the full names are long bilingual strings that would blow out
 * the component table's row width.
 *
 * Existing rows are backfilled with the CURRENT modem model of each machine —
 * the same "current state stamped with a date" semantics that
 * `ops:snapshot-daily --from --to` produces for every other state column. Done in
 * id chunks to keep each statement short on a ~650k-row table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('modem_type_id')->nullable()->after('card_terminal_name');
            $table->string('modem_type_name')->nullable()->after('modem_type_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn(['modem_type_id', 'modem_type_name']);
        });
    }

    /**
     * Stamp today's modem model onto historical rows, 20k ids at a time.
     */
    private function backfill(): void
    {
        $min = (int) DB::table('ops_machine_daily_snapshots')->min('id');
        $max = (int) DB::table('ops_machine_daily_snapshots')->max('id');

        if ($max === 0) {
            return;
        }

        $chunk = 20000;

        for ($start = $min; $start <= $max; $start += $chunk) {
            DB::statement(
                <<<'SQL'
                UPDATE ops_machine_daily_snapshots AS s
                JOIN vends AS v ON v.id = s.vend_id
                LEFT JOIN modem_types AS mt ON mt.id = v.modem_type_id
                SET s.modem_type_id = v.modem_type_id,
                    s.modem_type_name = COALESCE(NULLIF(mt.alias, ''), mt.name)
                WHERE s.id >= ? AND s.id < ?
                SQL,
                [$start, $start + $chunk]
            );
        }
    }
};
