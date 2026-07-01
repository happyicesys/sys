<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coin-float change history.
 *
 * One row is written ONLY when a machine's coin float (VENDER packet's
 * parameter_json.CoinCnt) changes AND the coin acceptor is active
 * (CHGEStat IN (1,3)). Detection happens inline in the VENDER ingest job
 * (App\Jobs\Vend\SyncVendParameter) by comparing the incoming CoinCnt with
 * vends.last_coin_cnt — an O(1) column read on the already-loaded model, so
 * no table crawl per packet.
 *
 * Deliberately lean: raw integer amounts (same base unit as parameter_json,
 * divide by 10^currency_exponent for display), no updated_at, single
 * composite index for the per-machine time-ordered read. Pruned to a 14-day
 * window nightly by `delete:vend-coin-float-log`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_coin_float_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vend_id');
            $table->integer('vend_code')->nullable();      // denormalized for display/CSV, no join
            $table->integer('coin_cnt');                   // parameter_json.CoinCnt at change time
            $table->integer('prev_coin_cnt')->nullable();  // value immediately before this change
            $table->integer('delta')->nullable();          // coin_cnt - prev_coin_cnt (null on first observation)
            $table->smallInteger('coin_stat')->nullable(); // parameter_json.CHGEStat at change time
            $table->timestamp('created_at')->nullable();

            // Sole read pattern: latest N for one machine within a date window.
            $table->index(['vend_id', 'created_at'], 'vcfl_vend_created');
        });

        Schema::table('vends', function (Blueprint $table) {
            // Last observed coin float, so change-detection is an O(1) compare
            // against the already-loaded vend instead of reading the log table.
            $table->integer('last_coin_cnt')->nullable()->after('parameter_json');
            $table->timestamp('last_coin_cnt_at')->nullable()->after('last_coin_cnt');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['last_coin_cnt', 'last_coin_cnt_at']);
        });

        Schema::dropIfExists('vend_coin_float_logs');
    }
};
