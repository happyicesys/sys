<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Date (no time) the bound "Upcoming Product Mapping" is scheduled to take
     * over. Nullable: only meaningful when upcoming_product_mapping_id is set.
     */
    public function up(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->date('upcoming_product_mapping_start_date')
                ->nullable()
                ->after('upcoming_product_mapping_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->dropColumn('upcoming_product_mapping_start_date');
        });
    }
};
