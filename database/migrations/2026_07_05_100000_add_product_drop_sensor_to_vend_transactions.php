<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product Drop Sensor state (enabled/disabled) FROZEN at the moment of the
     * vending transaction. Sourced from vends.parameter_json->Sensor (odd =
     * Enabled, even = Disabled), snapshotted onto the row so a later machine
     * toggle never rewrites history. Nullable = unknown (legacy rows / Sensor
     * value absent). Powers the Refund Index "Prod Exit Sensor" column.
     */
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->boolean('product_drop_sensor')->nullable()->after('dispensed_qty');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('product_drop_sensor');
        });
    }
};
