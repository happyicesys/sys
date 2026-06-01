<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site-level contact fields stored directly on the customers table, distinct
     * from the polymorphic billing Contact relation. Phone is a plain string —
     * no country code, since this is a single-country localized deployment.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('site_contact_person')->nullable()->after('name');
            $table->string('site_phone_number')->nullable()->after('site_contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['site_contact_person', 'site_phone_number']);
        });
    }
};
