<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add covering index on selling_prices(product_id, type, amount).
     *
     * VendChannel::getServerAmountAttribute() runs two queries:
     *
     *   1. SELECT server_price_type FROM vends WHERE id = ?
     *   2. SELECT amount FROM selling_prices
     *      WHERE product_id = ? AND type = ?
     *
     * Query 1 is a PK lookup — fast, unavoidable heap read (now returning a
     * single integer instead of the full row after the SELECT * fix).
     *
     * Query 2 previously had no index on (product_id, type). Without one,
     * MySQL does a full table scan of selling_prices for every VendChannel
     * that has a server_price_type set. This accessor is called for every
     * VendChannel serialised in the vend-channel listing page.
     *
     * With (product_id, type, amount):
     *   - product_id + type narrow to exactly the target row
     *   - amount is in the index leaf — zero heap access (covering index)
     */
    public function up(): void
    {
        if (!Schema::hasIndex('selling_prices', 'idx_selling_prices_product_type_amount')) {
            Schema::table('selling_prices', function (Blueprint $table) {
                $table->index(
                    ['product_id', 'type', 'amount'],
                    'idx_selling_prices_product_type_amount'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('selling_prices', 'idx_selling_prices_product_type_amount')) {
            Schema::table('selling_prices', function (Blueprint $table) {
                $table->dropIndex('idx_selling_prices_product_type_amount');
            });
        }
    }
};
