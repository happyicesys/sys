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
            $table->unsignedBigInteger('previous_ops_job_item_id')->nullable()->index();
            $table->integer('acc_total_amount')->default(0);
            $table->integer('acc_total_count')->default(0);
            $table->unsignedBigInteger('vend_channel_record_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('previous_ops_job_item_id');
            $table->dropColumn('acc_total_amount');
            $table->dropColumn('acc_total_count');
            $table->dropColumn('vend_channel_record_id');
        });
    }
};
