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
            $table->unsignedBigInteger('vend_prefix_id')->nullable()->after('vend_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_vend_bindings', function (Blueprint $table) {
            $table->dropColumn('vend_prefix_id');
        });
    }
};
