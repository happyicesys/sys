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
        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropColumn('customer_json');
        });

        Schema::table('vend_snapshots', function (Blueprint $table) {
            $table->dropColumn('customer_json');
        });

        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('vend_json');
            $table->dropColumn('error_json');
        });

        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropColumn('error_json');
        });

        Schema::dropIfExists('delivery_platform_logs');

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->json('meta_json')->nullable();
            $table->dropColumn('customer_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
