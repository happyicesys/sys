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
            $table->dropColumn('vend_transaction_items_json');
            $table->bigInteger('vend_contract_id')->nullable()->after('vend_channel_code');
            $table->bigInteger('vend_model_id')->nullable()->after('vend_channel_code');
            $table->bigInteger('vend_prefix_id')->nullable()->after('vend_channel_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn(['vend_contract_id', 'vend_model_id', 'vend_prefix_id']);
        });
    }
};
