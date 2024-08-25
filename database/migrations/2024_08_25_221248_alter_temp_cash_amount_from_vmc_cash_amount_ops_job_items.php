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
            $table->integer('temp_cash_amount_from_vmc')->nullable()->default(null)->after('status')->change();
            $table->integer('cash_amount')->nullable()->default(null)->after('id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->integer('temp_cash_amount_from_vmc')->default(0)->after('status')->change();
            $table->integer('cash_amount')->default(0)->after('id')->change();
        });
    }
};
