<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment method can belong to one machine-menu variant only (Brian,
 * 2026-10-29). The machine menu API takes an optional tail —
 * /api/v1/payment-merchants/SG/omise/mindef — and a method whose
 * `menu_variant` is set shows only on that tail, on top of the normal list.
 * NULL (every existing row) = the normal menu, unchanged.
 *
 * Its own migration, pushed ahead of the code that reads it: the deploy serves
 * new code before `migrate`, and the menu API is hit by every board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('menu_variant', 32)->nullable()->after('type_name');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('menu_variant');
        });
    }
};
