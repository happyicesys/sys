<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who set each selling price and what it replaced (Brian, 2026-10-12). A price is never edited in
 * place — Product → Edit deletes the row and adds a new one — so `created_at` is when this price was
 * set, `created_by` who set it and `previous_amount` (cents) the same RP's price before it.
 * Existing rows: SellingPriceAuthorBackfillSeeder, from user_logs (2026-07-18 onwards).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('selling_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->integer('previous_amount')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('selling_prices', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'previous_amount']);
        });
    }
};
