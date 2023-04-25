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
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('type')->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('location_type_id')->change();
        });

        Schema::table('vends', function (Blueprint $table) {
            $table->index('product_mapping_id')->change();
        });

        Schema::table('vend_bindings', function (Blueprint $table) {
            $table->index('customer_id')->change();
        });

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->index('created_at')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            //
        });
    }
};
