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
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->json('operator_json')->after('unit_cost_id')->nullable();
            $table->json('location_type_json')->after('unit_cost_id')->nullable();
            $table->json('customer_json')->after('unit_cost_id')->nullable();
            $table->dropColumn('unit_cost_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('customer_json');
            $table->dropColumn('operator_json');
            $table->dropColumn('location_type_json');
        });
    }
};
