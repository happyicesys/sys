<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index customers.notes_updated_at for the @-mention / unread-note badges.
 *
 * NoteNotificationService::customerMentionUnreadCount (and the unread-note
 * badge) add `notes_updated_at > ?` once the user has visited the page
 * (last_viewed_at is set). Without an index that predicate can't seek, so the
 * count falls back to scanning customers and running the LIKE/REGEXP mention
 * test on every row. With the index, the bounded (post-first-visit) counts seek
 * straight to recently-changed notes — a tiny set — before the LIKE/REGEXP.
 *
 * Behaviour-preserving: index only, no query/logic change. customers is not a
 * hot-write table for notes, so the extra write cost is negligible.
 *
 * NOTE: this does NOT speed the never-visited (last_viewed_at NULL => $since
 * NULL) path, which has no time bound and still scans by design. The durable
 * fix for that is denormalising mentions into their own indexed table — a
 * separate, larger change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'notes_updated_at')) {
            return;
        }
        if (! Schema::hasIndex('customers', 'idx_customers_notes_updated_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('notes_updated_at', 'idx_customers_notes_updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customers', 'idx_customers_notes_updated_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('idx_customers_notes_updated_at');
            });
        }
    }
};
