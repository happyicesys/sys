<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add category + provenance to the manually-managed holidays table so the
     * public-holiday sync can coexist with hand-entered rows.
     *
     *   type   : public | school | other   (NULL for pre-existing manual rows)
     *   source : manual | api | seeder      (existing rows default to 'manual'
     *            so the sync never treats them as its own to overwrite/delete)
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('name');
            $table->string('source', 32)->default('manual')->after('type');
            $table->index('source');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'source']);
        });
    }
};
