<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->boolean('is_zero_amount')->default(false)->after('amount')->index();
        });

        // Backfill existing
        DB::table('vend_transactions')->where('amount', 0)->update(['is_zero_amount' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('is_zero_amount');
        });
    }
};
