<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The online / offline history of each remote card terminal (Payrallel T05), Brian 2026-11-19.
 *
 * One row per stretch of the same answer: a new row only when the answer changes (online ↔ offline ↔ the
 * check itself failed) or after a gap no check covered. Written only by TerminalStatusLog. Change-only, so
 * a terminal adds a few rows a day: no prune (the per-check HTTP lines it is read from are pruned at 30 days).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_card_terminal_status_spans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_card_terminal_id');
            // The physical T05 (Data Management → Card Terminal) and the freezer it sat on, both at the time:
            // a terminal moved to another freezer keeps its own history apart from the new freezer's.
            $table->unsignedBigInteger('card_terminal_unit_id')->nullable();
            $table->unsignedBigInteger('vend_id')->nullable();
            // true = online, false = offline, NULL = the check failed (Payrallel unreachable or refused).
            $table->boolean('online')->nullable();
            $table->string('last_state', 32)->nullable();
            $table->string('error', 255)->nullable();
            $table->dateTime('started_at', 3);
            $table->dateTime('last_seen_at', 3);
            $table->unsignedInteger('checks')->default(1);
            $table->timestamps();

            $table->index(['remote_card_terminal_id', 'started_at'], 'rct_status_spans_terminal_started');
            $table->index(['card_terminal_unit_id', 'started_at'], 'rct_status_spans_unit_started');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_card_terminal_status_spans');
    }
};
