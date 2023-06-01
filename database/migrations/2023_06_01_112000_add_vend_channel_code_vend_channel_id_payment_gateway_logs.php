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
            $table->string('vend_channel_code')->nullable()->after('vend_code');
            $table->bigInteger('vend_channel_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropColumn('vend_channel_code');
            $table->dropColumn('vend_channel_id');
        });
    }
};
