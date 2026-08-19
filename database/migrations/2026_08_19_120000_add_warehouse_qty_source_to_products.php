<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.warehouse_qty_source (§8.1): 'cms' (default — today's behaviour for
 * every existing product, zero change) or 'ledger'. CityBox-mapped products
 * are set to 'ledger' at mapping time; a human may flip either way on the
 * product edit page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('warehouse_qty_source', 10)->default('cms')->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('warehouse_qty_source');
        });
    }
};
