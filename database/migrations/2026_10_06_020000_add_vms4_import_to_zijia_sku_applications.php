<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products approved through Zijia's vms4 portal are mirrored into mark1 as applications too
 * (2026-10-06): `source` says where the application was made, `library_entry` keeps Zijia's
 * library record as read (with the map of their photo URLs to our copies), `library_updated_at`
 * is their `updatedTime`, so a change made in vms4 is picked up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zijia_sku_applications', function (Blueprint $table) {
            $table->string('source', 10)->default('mark1')->after('status')->index();
            $table->json('library_entry')->nullable()->after('last_error');
            $table->timestamp('library_updated_at')->nullable()->after('library_entry');
        });
    }

    public function down(): void
    {
        Schema::table('zijia_sku_applications', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'library_entry', 'library_updated_at']);
        });
    }
};
