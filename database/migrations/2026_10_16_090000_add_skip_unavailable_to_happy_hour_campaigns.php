<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "No discount on 'as current' not Available SKUs" (Brian, 2026-10-16): while ticked, a SKU whose
 * warehouse Available? tick (`products.is_available`, Product Movement) is off is never picked for a
 * Happy Hour slot. On by default, existing campaigns included.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->boolean('skip_unavailable')->default(true)->after('allow_below_cost');
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropColumn('skip_unavailable');
        });
    }
};
