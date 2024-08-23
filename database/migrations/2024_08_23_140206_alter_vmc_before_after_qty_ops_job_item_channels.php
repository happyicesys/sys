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
            $table->integer('vmc_before_qty')->nullable()->default(null)->after('vend_code')->change();
            $table->integer('vmc_after_qty')->nullable()->default(null)->after('vmc_before_qty')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {

        });
    }
};
