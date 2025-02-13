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
        Schema::table('dispense_records', function (Blueprint $table) {
            $table->bigInteger('delivery_platform_order_id')->nullable()->index()->after('payment_gateway_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispense_records', function (Blueprint $table) {
            $table->dropColumn('delivery_platform_order_id');
        });
    }
};
