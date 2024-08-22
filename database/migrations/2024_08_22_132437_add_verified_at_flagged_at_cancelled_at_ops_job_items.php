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
            $table->bigInteger('created_by')->nullable();
            $table->datetime('verified_at')->nullable();
            $table->bigInteger('verified_by')->nullable();
            $table->datetime('flagged_at')->nullable();
            $table->bigInteger('flagged_by')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->bigInteger('cancelled_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('created_by');
            $table->dropColumn('verified_at');
            $table->dropColumn('verified_by');
            $table->dropColumn('flagged_at');
            $table->dropColumn('flagged_by');
            $table->dropColumn('cancelled_at');
            $table->dropColumn('cancelled_by');
        });
    }
};
