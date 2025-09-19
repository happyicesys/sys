<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->bigInteger('delivery_platform_ref_number_id')->nullable()->index('dpo_dp_ref_num_idx');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropIndex('dpo_dp_ref_num_idx');
            $table->dropColumn('delivery_platform_ref_number_id');
        });
    }
};

