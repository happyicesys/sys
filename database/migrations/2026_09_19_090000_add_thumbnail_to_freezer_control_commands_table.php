<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the small copy of a camera still lives (App\Services\Freezer\FreezerPhotoThumbnail).
 * Nullable and filled lazily: photos taken before this migration get theirs the first time the
 * panel asks for one, so there is nothing to backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freezer_control_commands', function (Blueprint $table) {
            $table->string('attachment_thumb_path')->nullable()->after('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::table('freezer_control_commands', function (Blueprint $table) {
            $table->dropColumn('attachment_thumb_path');
        });
    }
};
