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
        // Schema::table('vend_transactions', function (Blueprint $table) {
        //     $table->dropColumn('operator_json');
        //     $table->dropColumn('product_json');
        //     $table->dropColumn('vend_binding_id');
        //     $table->dropColumn('vend_json');


        //     $table->bigInteger('location_type_id')->nullable()->index()->after('location_type_json');
        // });

        Schema::dropIfExists('vend_transactions_bk');


        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->dropColumn('product_json');
            $table->dropColumn('vend_channel_error_json');

            $table->integer('vend_channel_error_code')->nullable()->after('vend_channel_id');
        });

        Schema::dropIfExists('vend_transaction_items_bk');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
