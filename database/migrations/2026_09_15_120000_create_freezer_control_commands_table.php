<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remote cabinet controls for smart freezers (FreezerControlService).
 *
 * `freezer_control_commands` is the audit trail AND the correlation table: every button press on
 * Setting/Edit becomes one row keyed by a ULID `cmd_id` that travels in the FREEZERCTL frame and
 * comes back in the device's FREEZERCTLACK. Rows are never updated by the browser, only by the ack.
 *
 * The two `vends` columns hold the latest full status snapshot the device sent with any ack, kept
 * apart from `freezer_status_json` (the periodic FREEZERSTATUS packet) because the snapshot is
 * richer, arrives on demand, and is replaced whole rather than merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freezer_control_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->char('cmd_id', 26)->unique();
            $table->string('op', 20);
            $table->json('args')->nullable();
            // pending until the device answers; then one of the FREEZERCTLACK results.
            $table->string('status', 20)->default('pending');
            $table->string('response_msg', 255)->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['vend_id', 'id']);
        });

        Schema::table('vends', function (Blueprint $table) {
            $table->json('freezer_control_status_json')->nullable()->after('freezer_status_json');
            $table->timestamp('freezer_control_status_at')->nullable()->after('freezer_control_status_json');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['freezer_control_status_json', 'freezer_control_status_at']);
        });
        Schema::dropIfExists('freezer_control_commands');
    }
};
