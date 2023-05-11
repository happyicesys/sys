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
            $table->bigInteger('operator_id')->nullable()->after('amount')->index();
            $table->bigInteger('customer_id')->nullable()->after('amount')->index();
            $table->integer('vend_channel_code')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('operator_id');
            $table->dropColumn('vend_channel_code');
        });
    }
};
