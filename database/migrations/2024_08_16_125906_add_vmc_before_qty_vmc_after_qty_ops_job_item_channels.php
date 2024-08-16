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
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->integer('vmc_before_qty')->default(0)->after('vend_code');
            $table->integer('vmc_after_qty')->default(0)->after('vend_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->dropColumn('vmc_before_qty');
            $table->dropColumn('vmc_after_qty');
        });
    }
};
