<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terminal reversals from the settlement report (2026-09-02).
 *
 * A NETS reversal is a SEPARATE report line — "Reversal Code = Y", negative
 * amount, same terminal, stamped at the moment the reader reversed (the
 * "Void Txn Indicator" column is never set in practice). The matcher pairs
 * each reversal line with the purchase line it undoes, and Sync then marks
 * that purchase's sale refunded (auto_refund_source =
 * settlement_report_reversal). The report is the authority: the TRADE-time
 * reversal heuristic (config refund.card_reversal_terminals) is switched off
 * for NETS in the same change.
 *
 *  - is_reversal          parsed from the file
 *  - reverses_row_id      on the reversal line → the purchase line it undoes
 *  - reversed_by_row_id   on the purchase line → its reversal line
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_settlement_rows', function (Blueprint $table) {
            $table->boolean('is_reversal')->default(false)->after('sequence_no');
            $table->unsignedBigInteger('reverses_row_id')->nullable()->after('is_reversal');
            $table->unsignedBigInteger('reversed_by_row_id')->nullable()->after('reverses_row_id');
        });

        Schema::table('card_settlement_reports', function (Blueprint $table) {
            // Which filesystem disk holds the uploaded file (private DO Spaces
            // in prod, 'local' without credentials) — the download route and
            // the ingest job read from here, never from the default disk.
            $table->string('storage_disk', 32)->nullable()->after('original_filename');
            $table->unsignedInteger('reversal_rows')->default(0)->after('purchase_rows');
            $table->unsignedInteger('refunded_count')->default(0)->after('synced_count');
        });
    }

    public function down(): void
    {
        Schema::table('card_settlement_rows', function (Blueprint $table) {
            $table->dropColumn(['is_reversal', 'reverses_row_id', 'reversed_by_row_id']);
        });

        Schema::table('card_settlement_reports', function (Blueprint $table) {
            $table->dropColumn(['storage_disk', 'reversal_rows', 'refunded_count']);
        });
    }
};
