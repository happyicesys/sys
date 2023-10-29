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
            $table->string('driver_phone_number')->nullable()->after('driver_eta_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('driver_phone_number');
        });
    }
};
