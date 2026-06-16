<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short display alias for a user (e.g. "B" for Brian), keyed in by the user
 * themselves. Used by the @-mention dropdown: when set, the dropdown shows
 * "Name (alias)" and inserts the shorter "@alias" so notes stay compact.
 * Nullable — falls back to the full name when blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
