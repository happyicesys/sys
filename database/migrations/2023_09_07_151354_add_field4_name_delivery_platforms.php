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
        Schema::table('delivery_platforms', function (Blueprint $table) {
            $table->string('field4_name')->nullable()->after('field3_name');
        });

        Schema::table('delivery_platform_operators', function (Blueprint $table) {
            $table->string('field4')->nullable()->after('field3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platforms', function (Blueprint $table) {
            $table->dropColumn('field4_name');
        });

        Schema::table('delivery_platform_operators', function (Blueprint $table) {
            $table->dropColumn('field4');
        });
    }
};
