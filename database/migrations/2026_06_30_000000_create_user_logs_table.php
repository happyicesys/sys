<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic, app-wide user-action audit log.
 *
 * One row per Create / Update / Delete / Restore performed by an authenticated
 * web user. Machine ingestion (VendDataService HTTP/MQTT), cron, and queue
 * workers are intentionally NOT recorded (they have no web actor) — see
 * App\Services\UserLogger for the gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The "who". user_name is a denormalized snapshot so the log stays
            // readable even if the user is later renamed or deleted.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();

            $table->string('event', 20); // created | updated | deleted | restored

            // The "what" (polymorphic target).
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            // updated -> {field: [old, new]}; created/deleted -> full snapshot.
            $table->json('changes')->nullable();

            $table->string('source', 20)->default('web');
            $table->string('ip', 45)->nullable();
            $table->string('url', 2048)->nullable();

            // Immutable row: "when" only, never updated.
            $table->timestamp('created_at')->nullable()->index();

            // Entity history, newest-first via id (keyset "load older").
            $table->index(['auditable_type', 'auditable_id', 'id'], 'user_logs_entity_idx');
            // Type-wide history for Index-page toolbars.
            $table->index(['auditable_type', 'id'], 'user_logs_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
