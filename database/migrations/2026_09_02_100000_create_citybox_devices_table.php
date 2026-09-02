<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CityBox fleet as mark1 last saw it — one row per `box_list` device,
 * linked or not. Written ONLY by the poller / on-demand refresh (upsert);
 * there is no create, update or delete path in the UI, because their API is
 * the single source of truth for a device's identity and state.
 *
 * Why a table and not the 60 s cache it replaces: unlinked devices were not
 * rows anywhere, so the Create-page picker needed a live API call and the
 * portal cross-reference could not be queried. A device that drops out of
 * `box_list` keeps its row with a stale `last_seen_at` — never deleted.
 *
 * vends.citybox_equipment_id stays the join key (by code, no FK): the poller
 * and every service already key on it, and a UNIQUE varchar join is cheap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citybox_devices', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_id', 64)->unique();       // 设备号 — the only join key
            $table->string('name')->nullable();                 // box_list.name (NOT their portal "Machine Name")
            $table->string('type', 32)->nullable();             // visual-2 | visual-8 | …
            $table->smallInteger('ops_status')->nullable();     // 0 禁运 / 1 启运 / 98 撤机中 / 99 已撤机
            $table->string('ops_status_label', 32)->nullable();
            $table->boolean('online')->default(false);
            $table->timestamp('heartbeat_last_recovery')->nullable();
            $table->timestamp('heartbeat_last_offline')->nullable();
            // Not on box_list today (asked 2026-09-02); land here when CityBox adds them.
            $table->string('client_name')->nullable();
            $table->string('location')->nullable();
            $table->string('address')->nullable();
            // Present in the most recent COMPLETE box_list sweep. A filtered refresh
            // (one device) never touches it; a complete sweep sets it for the rows
            // listed and clears it for the rest. "Current fleet" = in_fleet, not a
            // time window — a device that vanished 30 s ago is already gone.
            $table->boolean('in_fleet')->default(true)->index();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_devices');
    }
};
