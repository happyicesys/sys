<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the "any allowed item in this basket" EXISTS leg of the product
 * access predicate (see App\Support\ProductAccess::transactionPredicate).
 *
 * The existing indexes are (vend_transaction_id) and (product_id,
 * vend_channel_id); neither lets the semi-join stay index-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->index(['vend_transaction_id', 'product_id'], 'idx_vti_txn_product');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->dropIndex('idx_vti_txn_product');
        });
    }
};
