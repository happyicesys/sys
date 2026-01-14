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
        Schema::table('vends', function (Blueprint $table) {
            $table->boolean('is_txn_src')->default(false)->after('last_cashless_vend_transaction_at');
            $table->dateTime('last_txn_src_at')->nullable()->after('is_txn_src');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['is_txn_src', 'last_txn_src_at']);
        });
    }
};
