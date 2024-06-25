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
        Schema::dropIfExists('product_mapping_vend_prefixes');
        Schema::table('vend_prefixes', function (Blueprint $table) {
            $table->unsignedBigInteger('product_mapping_id')->nullable()->after('operator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_prefixes', function (Blueprint $table) {
            $table->dropColumn('product_mapping_id');
        });
    }
};
