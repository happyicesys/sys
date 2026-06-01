<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make contacts.name nullable.
 *
 * The Billing Contact's "Contact Person" is now intentionally left blank — the
 * real site contact moved to customers.site_contact_person (filled from CMS
 * "Att To"). The CMS backfill writes a Billing Contact row that only carries
 * Company + Email, so name must be allowed to be NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
