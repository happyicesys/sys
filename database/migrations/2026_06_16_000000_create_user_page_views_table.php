<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user "last viewed this page" tracker, backing the messenger-style unread
 * badges on Site Summary (/customers/summary → customers.notes) and Warehouse
 * Qty & Planning (/products/availability → products.remarks).
 *
 *   last_viewed_at : timestamp of the user's most recent FRESH arrival on the
 *                    page. The sidebar unread badge counts note/remark changes
 *                    made by OTHER users after this moment, so it resets to 0
 *                    the instant the user opens the page ("clear on visit").
 *   unread_since   : start of the current unread window = the value of
 *                    last_viewed_at *before* the most recent fresh arrival. The
 *                    on-page "Unread" button filters to rows changed since this
 *                    timestamp, so it still has something to show even though
 *                    the sidebar badge already cleared. Untouched by in-page
 *                    filter re-searches (which carry searched=1).
 *
 * One row per (user, page_key); page_key is e.g. 'customers.summary' /
 * 'products.availability'. Additive only — no FK constraint (soft link by
 * user_id) to stay friendly with the legacy schema, matching the banks table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_page_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('page_key');
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('unread_since')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'page_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_page_views');
    }
};
