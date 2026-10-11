<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The operator that owns a Happy Hour campaign (Brian, 2026-10-12): an operator-restricted admin
 * sees, edits and deletes only its own operator's campaigns (`OperatorHappyHourCampaignScope`).
 * Existing rows take their creator's operator, else HIPL (1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('operator_id')->nullable()->after('name')->index();
        });

        DB::table('happy_hour_campaigns as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->whereNull('c.operator_id')
            ->update(['c.operator_id' => DB::raw('COALESCE(NULLIF(u.operator_id, 0), 1)')]);
    }

    public function down(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropIndex(['operator_id']);
            $table->dropColumn('operator_id');
        });
    }
};
