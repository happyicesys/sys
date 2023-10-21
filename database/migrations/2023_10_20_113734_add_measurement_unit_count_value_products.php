<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('measurement_count')->default(1)->after('is_inventory');
            $table->string('measurement_unit')->nullable()->after('is_inventory');
            $table->unsignedInteger('measurement_value')->nullable()->after('is_inventory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'measurement_count',
                'measurement_unit',
                'measurement_value',
            ]);
        });
    }
};
