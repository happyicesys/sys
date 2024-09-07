<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->datetime('undo_picked_at')->nullable()->after('picked_by');
            $table->unsignedBigInteger('undo_picked_by')->nullable()->after('picked_by');
            $table->datetime('undo_verified_at')->nullable()->after('verified_by');
            $table->unsignedBigInteger('undo_verified_by')->nullable()->after('verified_by');
            $table->datetime('undo_completed_at')->nullable()->after('completed_by');
            $table->unsignedBigInteger('undo_completed_by')->nullable()->after('completed_by');
            $table->datetime('undo_flagged_at')->nullable()->after('flagged_by');
            $table->unsignedBigInteger('undo_flagged_by')->nullable()->after('flagged_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('undo_picked_at');
            $table->dropColumn('undo_picked_by');
            $table->dropColumn('undo_verified_at');
            $table->dropColumn('undo_verified_by');
            $table->dropColumn('undo_completed_at');
            $table->dropColumn('undo_completed_by');
            $table->dropColumn('undo_flagged_at');
            $table->dropColumn('undo_flagged_by');
        });
    }
};
