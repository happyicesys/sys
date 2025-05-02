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
        Schema::table('voucher_items', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false);
            $table->datetime('locked_at')->nullable();
            $table->bigInteger('locked_by_vend_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_items', function (Blueprint $table) {
            $table->dropColumn('is_locked');
            $table->dropColumn('locked_at');
            $table->dropColumn('locked_by_vend_id');
        });
    }
};
