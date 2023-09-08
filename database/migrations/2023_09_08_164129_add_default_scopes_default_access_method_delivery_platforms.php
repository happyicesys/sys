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
            $table->json('default_scopes')->nullable();
            $table->json('default_access_method')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platforms', function (Blueprint $table) {
            $table->dropColumn('default_scopes');
            $table->dropColumn('default_access_method');
        });
    }
};
