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
            $table->integer('temp_cash_amount_from_vmc')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('temp_cash_amount_from_vmc');
        });
    }
};
