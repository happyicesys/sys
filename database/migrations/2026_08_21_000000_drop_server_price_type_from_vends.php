<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Site (customers.selling_price_type, RP1–RP5) is the ONLY source of the
 * reference price tier. A machine no longer carries its own RP choice — it only
 * says whether it follows the Site's pricing (vends.is_using_server_price) or
 * the VMC board price.
 *
 * Until now Machine Settings stored a second, independent RP on
 * vends.server_price_type ("--- Not Using ---" / RP1–RP5). Backfill the flag
 * from it (any RP set == "using server price") and drop the column so the two
 * can never disagree again. Brian, 2026-08-21.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vends', 'server_price_type')) {
            DB::table('vends')->update([
                'is_using_server_price' => DB::raw('CASE WHEN server_price_type IS NULL THEN 0 ELSE 1 END'),
            ]);

            Schema::table('vends', function (Blueprint $table) {
                $table->dropColumn('server_price_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->integer('server_price_type')->nullable()->after('serial_num');
        });

        // Best-effort restore: a machine that follows its Site gets the Site's
        // RP written back; the per-machine override is not recoverable.
        DB::statement('UPDATE vends v LEFT JOIN customers c ON c.id = v.customer_id
            SET v.server_price_type = c.selling_price_type
            WHERE v.is_using_server_price = 1');
    }
};
