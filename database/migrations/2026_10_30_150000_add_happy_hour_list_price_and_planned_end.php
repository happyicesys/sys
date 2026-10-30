<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Happy Hour audit 2026-10-30.
 *
 * `happy_hour_list_price` on a tagged sale line (a single sale's header, a basket's item): the price the
 * screen showed before the discount, frozen when HappyHourSales::tag() tags it. The discount had been
 * read from today's `vend_channels.amount`, so a later board-price change or channel re-map rewrote past
 * Happy Hour discounts (212 sales on 2026-10-30). Nullable, appended last, no index: an INSTANT add on
 * MySQL 8, like `happy_hour_slot_id` beside it.
 *
 * `planned_ends_at` on a slot: when it was planned to end. `ends_at` moves earlier when a slot is
 * cancelled, swapped or sold out; a board that missed the nudge keeps charging the promo until the
 * planned end, so its sales are tagged up to then. Existing rows take their `ends_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->unsignedInteger('happy_hour_list_price')->nullable();
        });
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->unsignedInteger('happy_hour_list_price')->nullable();
        });
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->dateTime('planned_ends_at')->nullable()->after('ends_at');
        });
        DB::table('happy_hour_slots')->whereNull('planned_ends_at')->update(['planned_ends_at' => DB::raw('ends_at')]);
    }

    public function down(): void
    {
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->dropColumn('planned_ends_at');
        });
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->dropColumn('happy_hour_list_price');
        });
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('happy_hour_list_price');
        });
    }
};
