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
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->boolean('virtual_is_error')->virtualAs("(capacity - (capacity - vmc_before_qty)) + actual_qty != vmc_after_qty")->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->boolean('virtual_is_error')->virtualAs("(qty + actual_qty) != vmc_after_qty")->change();
        });
    }
};
