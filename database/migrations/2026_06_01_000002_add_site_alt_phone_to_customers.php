<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alternate site-level phone number, stored on the customers table alongside
     * site_phone_number. Plain string — no country code (single-country
     * localized deployment).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('site_alt_phone_number')->nullable()->after('site_phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('site_alt_phone_number');
        });
    }
};
