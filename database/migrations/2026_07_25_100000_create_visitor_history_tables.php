<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin > Visitor History — who logged in, from where, and what they opened.
 *
 * Two tables on purpose:
 *
 *   visitor_sessions     one row per login session (user + IP + device), with
 *                        login_at / last_activity_at / ended_at. `ended_at` is
 *                        only exact when the user clicked Log Out (end_reason
 *                        'logout') or the browser fired its unload beacon
 *                        ('closed'); a session that just goes quiet is shown as
 *                        "expired (inferred)" at query time from
 *                        last_activity_at + session lifetime, so no cron is
 *                        needed to keep the table honest.
 *
 *   visitor_page_views   one row per Inertia page opened. `visit_uuid` is minted
 *                        by LogVisitorActivity BEFORE the response renders and
 *                        shared to the frontend as an Inertia prop, so the
 *                        browser beacon can post real dwell time back against
 *                        the exact row. duration_source records whether the
 *                        number came from that beacon or was merely inferred
 *                        from the next page view's timestamp.
 *
 * No foreign-key constraints: this is an append-only audit log that must never
 * be able to block a user delete or a page render.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_type', 16)->nullable();      // desktop | mobile | tablet | bot
            $table->string('platform', 40)->nullable();         // Windows | macOS | iOS | Android ...
            $table->string('browser', 40)->nullable();          // Chrome | Safari | Edge ...
            $table->string('browser_version', 24)->nullable();
            $table->timestamp('login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 16)->nullable();       // logout | closed
            $table->unsignedInteger('page_view_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'login_at']);
            $table->index('login_at');
        });

        Schema::create('visitor_page_views', function (Blueprint $table) {
            $table->id();
            $table->char('visit_uuid', 36)->unique();
            $table->unsignedBigInteger('visitor_session_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('path', 191);
            $table->string('query_string', 500)->nullable();
            $table->string('route_name', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('active_seconds')->nullable();
            $table->string('duration_source', 12)->nullable();  // beacon | inferred
            $table->timestamps();

            $table->index(['user_id', 'viewed_at']);
            $table->index(['visitor_session_id', 'viewed_at']);
            $table->index(['path', 'viewed_at']);
            $table->index('viewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_page_views');
        Schema::dropIfExists('visitor_sessions');
    }
};
