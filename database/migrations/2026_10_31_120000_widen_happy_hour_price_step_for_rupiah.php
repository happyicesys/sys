<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rupiah price steps (Brian, 2026-10-31; HappyHourPriceStep). Steps are stored in minor units, so Rp 1,000 is
 * 100000 — past a SMALLINT's 65535. Any campaign owned by a rupiah operator moves to Rp 1,000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('price_step_cents')->default(10)->change();
        });

        DB::table('happy_hour_campaigns')
            ->whereIn('operator_id', DB::table('operators')
                ->join('countries', 'countries.id', '=', 'operators.country_id')
                ->where('countries.currency_name', 'IDR')
                ->select('operators.id'))
            ->whereNotIn('price_step_cents', [10000, 50000, 100000])
            ->update(['price_step_cents' => 100000]);
    }

    public function down(): void
    {
        // A rupiah step cannot fit back; drop it to the old largest step first.
        DB::table('happy_hour_campaigns')->where('price_step_cents', '>', 100)->update(['price_step_cents' => 100]);

        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('price_step_cents')->default(10)->change();
        });
    }
};
