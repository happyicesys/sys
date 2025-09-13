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
        Schema::table('customer_vend_bindings', function (Blueprint $table) {
            $table->unique(
                ['vend_id', 'customer_id', 'is_binding', 'created_at'],
                'uniq_vend_customer_binding_at'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_vend_bindings', function (Blueprint $table) {
            $table->dropUnique('uniq_vend_customer_binding_at');
        });
    }
};
