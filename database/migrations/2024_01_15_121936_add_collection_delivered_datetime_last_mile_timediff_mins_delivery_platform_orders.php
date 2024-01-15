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
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->datetime('collected_datetime')->nullable();
            $table->datetime('delivered_datetime')->nullable();
            $table->integer('last_mile_timediff_mins')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('collected_datetime');
            $table->dropColumn('delivered_datetime');
            $table->dropColumn('last_mile_timediff_mins');
        });
    }
};
