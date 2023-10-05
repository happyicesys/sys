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
        Schema::table('vends', function (Blueprint $table) {
            $table->integer('virtual_vend_records_thirty_days_amount_average')->virtualAs('json_unquote(vend_transaction_totals_json->"$.vend_records_thirty_days_amount_average")')->after('vend_transaction_totals_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('virtual_vend_records_thirty_days_amount_average');
        });
    }
};
