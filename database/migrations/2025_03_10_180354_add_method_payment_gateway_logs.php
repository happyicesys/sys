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
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->string('method')->nullable()->after('response');
            $table->dropColumn('vend_channel_code');
            $table->dropColumn('vend_channel_id');
            $table->dropColumn('vend_transaction_id');
            $table->datetime('approved_at')->nullable()->index();
        });

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->bigInteger('payment_gateway_log_id')->nullable()->index()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
