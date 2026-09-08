<?php

use App\Support\DispenseVerdict;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Code 99 — "Machine transaction not found (NA)" (Brian, 2026-09-08).
 *
 * A payment rail (Omise today, the NETS report later) received the money and
 * the machine's TRADE never arrived. Server-defined: the VMC/APK never emit
 * it (every SErr ever seen on 4.0M frames is in {0,3,4,5,6,7,8,9}) and
 * VendChannelError::forFrameCode() refuses it from a frame. Only the marking
 * jobs write it. Semantics live in App\Support\DispenseVerdict: counts as a
 * sale, is NOT dispensed, is NOT a machine fault. weightage 0.
 *
 * Seeded from a migration (not VendChannelErrorSeeder, which is fresh-install
 * only and commented out of DatabaseSeeder) so RefreshDatabase tests carry it.
 *
 * settings.missing_trade_marked_until is the nightly marker's watermark:
 * the day boundary up to which gateway rows without a TRADE have been marked
 * 99 (NA_ERROR_CODE_PLAN_2026-09-08.md, Part 1 C).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vend_channel_errors')->updateOrInsert(
            ['code' => DispenseVerdict::NOT_FOUND_CODE],
            ['desc' => 'Machine transaction not found (NA)', 'weightage' => 0, 'created_at' => now(), 'updated_at' => now()]
        );

        Schema::table('settings', function (Blueprint $table) {
            $table->dateTime('missing_trade_marked_until')->nullable()->after('payment_gateway_log_refund_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('missing_trade_marked_until');
        });

        // Only remove the reference row while nothing points at it.
        $id = DB::table('vend_channel_errors')->where('code', DispenseVerdict::NOT_FOUND_CODE)->value('id');
        if ($id && ! DB::table('vend_transactions')->where('vend_channel_error_id', $id)->exists()) {
            DB::table('vend_channel_errors')->where('id', $id)->delete();
        }
    }
};
