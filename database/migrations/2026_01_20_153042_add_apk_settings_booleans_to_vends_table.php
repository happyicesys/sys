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
            $table->boolean('is_enable_grab_collection')->nullable()->after('is_txn_src');
            $table->boolean('is_enable_soft_keyboard_qr_pay')->nullable()->after('is_enable_grab_collection');
            $table->boolean('is_enable_soft_keyboard_cash_pay')->nullable()->after('is_enable_soft_keyboard_qr_pay');
            $table->boolean('is_enable_soft_keyboard_credit_card_pay')->nullable()->after('is_enable_soft_keyboard_cash_pay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'is_enable_grab_collection',
                'is_enable_soft_keyboard_qr_pay',
                'is_enable_soft_keyboard_cash_pay',
                'is_enable_soft_keyboard_credit_card_pay'
            ]);
        });
    }
};
