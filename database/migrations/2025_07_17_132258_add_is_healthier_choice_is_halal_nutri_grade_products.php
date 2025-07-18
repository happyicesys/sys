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
            $table->boolean('is_healthier_choice')->default(false)->after('is_commission');
            $table->boolean('is_halal')->default(false)->after('is_commission');
            $table->string('nutri_grade')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_healthier_choice', 'is_halal', 'nutri_grade']);
        });
    }
};
