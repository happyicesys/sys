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
            $table->integer('acc_total_cash_amount')->default(0)->after('acc_total_amount');
            $table->integer('acc_total_cashless_amount')->default(0)->after('acc_total_amount');
            $table->integer('acc_total_promo_amount')->default(0)->after('acc_total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('acc_total_cash_amount');
            $table->dropColumn('acc_total_cashless_amount');
            $table->dropColumn('acc_total_promo_amount');
        });
    }
};
