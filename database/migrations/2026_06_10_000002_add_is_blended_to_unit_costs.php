<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — mark unit cost rows that are auto-generated from blind flavours.
 *
 * A blind parent's cost is the ratio-weighted blend of its children's current
 * unit costs. The generated row is flagged is_blended = true so the UI can show
 * an "auto-generated from blind flavours" badge, and so the cost observer can
 * cheaply tell a derived row apart from a normal one (no recursion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_costs', function (Blueprint $table) {
            if (!Schema::hasColumn('unit_costs', 'is_blended')) {
                $table->boolean('is_blended')->default(false)->after('product_mapping_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unit_costs', function (Blueprint $table) {
            if (Schema::hasColumn('unit_costs', 'is_blended')) {
                $table->dropColumn('is_blended');
            }
        });
    }
};
