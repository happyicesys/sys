<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design §6.1 / §6b.4.
 *  - ops_job_items: the restock session handle (latest msg_id from
 *    zyy_ls_open_door — a driver may open twice) and the stock-submit state
 *    machine (pending → ok | failed, retried by a queued job).
 *  - citybox_door_open_logs: append-only — every door open ever, from any
 *    screen, success or refusal, with their handles so a later submit ties to
 *    THIS open. Never pruned; the accountability record. Also the source of
 *    "visit windows" for the movement classifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->string('citybox_msg_id', 64)->nullable()->after('remarks');
            $table->string('citybox_submit_status', 12)->nullable()->index()->after('citybox_msg_id'); // pending | ok | failed
            $table->timestamp('citybox_submitted_at')->nullable()->after('citybox_submit_status');
            $table->unsignedTinyInteger('citybox_submit_attempts')->default(0)->after('citybox_submitted_at');
            $table->text('citybox_submit_error')->nullable()->after('citybox_submit_attempts');
        });

        Schema::create('citybox_door_open_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->index();
            $table->string('citybox_equipment_id', 64);
            $table->unsignedBigInteger('ops_job_item_id')->nullable()->index();
            $table->unsignedBigInteger('ops_job_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('source', 24);          // ops_job_page | ops_job_item_page | vend_settings | api
            $table->timestamp('requested_at')->index();
            $table->string('result', 12);          // opened | refused | error
            $table->string('msg_id', 64)->nullable();
            $table->string('open_log_id', 64)->nullable();
            $table->string('citybox_code', 64)->nullable();
            $table->text('citybox_message')->nullable();
            $table->string('device_state_before', 24)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['vend_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_door_open_logs');
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn(['citybox_msg_id', 'citybox_submit_status', 'citybox_submitted_at', 'citybox_submit_attempts', 'citybox_submit_error']);
        });
    }
};
