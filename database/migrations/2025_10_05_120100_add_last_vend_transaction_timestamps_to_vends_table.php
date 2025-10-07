<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->timestamp('last_vend_transaction_at')->nullable();
            $table->timestamp('last_cash_vend_transaction_at')->nullable();
            $table->timestamp('last_card_vend_transaction_at')->nullable();
            $table->timestamp('last_cashless_vend_transaction_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'last_vend_transaction_at',
                'last_cash_vend_transaction_at',
                'last_card_vend_transaction_at',
                'last_cashless_vend_transaction_at',
            ]);
        });
    }
};
