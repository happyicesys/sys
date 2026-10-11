<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Happy Hour slot priced a sale (Brian, 2026-10-11). Written once at TRADE ingest by
 * `HappyHourSales::tag()`: a single sale carries it on its header row, a multiple purchase on each
 * promo line AND on its header (one slot per machine at a time, so a sale can only touch one).
 *
 * Nullable, appended last, no default, no index and no foreign key: on MySQL 8 that is an INSTANT
 * add on the 4.7M-row `vend_transactions` (no table copy, no lock), and every reader reaches these
 * rows through the existing (vend_id, transaction_datetime) index first. The slot row it points at
 * is never hard-deleted once a sale can carry it (campaigns are soft-deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('happy_hour_slot_id')->nullable();
        });
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->unsignedBigInteger('happy_hour_slot_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->dropColumn('happy_hour_slot_id');
        });
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('happy_hour_slot_id');
        });
    }
};
