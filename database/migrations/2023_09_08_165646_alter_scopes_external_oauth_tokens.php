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
        Schema::table('external_oauth_tokens', function (Blueprint $table) {
            $table->string('scopes')->nullable()->change();
        });

        Schema::table('delivery_platforms', function (Blueprint $table) {
            $table->string('default_scopes')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
