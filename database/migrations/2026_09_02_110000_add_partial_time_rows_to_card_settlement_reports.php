<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many lines of an uploaded report lost their hour (Excel re-save turns
 * "23:12:41" into "12:41.0" — the hour is gone from the file bytes; Excel's
 * own formula bar then shows a fake "12:12:41 AM"). Surfaced on the report
 * so the user knows those lines could only be matched on mm:ss and can
 * re-download the raw portal CSV. 5 of the 30 August-2026 files were like this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_settlement_reports', function (Blueprint $table) {
            $table->unsignedInteger('partial_time_rows')->default(0)->after('reversal_rows');
        });
    }

    public function down(): void
    {
        Schema::table('card_settlement_reports', function (Blueprint $table) {
            $table->dropColumn('partial_time_rows');
        });
    }
};
