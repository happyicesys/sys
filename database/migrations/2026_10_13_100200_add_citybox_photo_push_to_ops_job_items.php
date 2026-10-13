<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Before/after restock photos on a CityBox chiller stop (2026-10-13): the photos themselves are
 * attachments (OpsJobItem::PHOTO_RESTOCK_BEFORE / _AFTER); these columns track sending their URLs
 * to CityBox, mirroring citybox_submit_*. Nullable, appended last: an instant ALTER on a big table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->string('citybox_photo_push_status', 12)->nullable(); // pending | ok | failed
            $table->timestamp('citybox_photo_pushed_at')->nullable();
            $table->string('citybox_photo_push_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn(['citybox_photo_push_status', 'citybox_photo_pushed_at', 'citybox_photo_push_error']);
        });
    }
};
