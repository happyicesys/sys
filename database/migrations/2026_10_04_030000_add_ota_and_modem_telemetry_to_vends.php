<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTA updater and square-module (Air724) state from the "P" heartbeat, big-board 307+
 * (apk/mark1-apk/UNRELEASED_V307.md, 2026-10-04):
 *
 *   OtaFail  -> ota_fail_streak   consecutive failed OTA polls/downloads, 0 = last poll fine
 *   OtaErr   -> ota_last_error    last failure reason while failing, null once a poll succeeds
 *   ModemFw  -> modem_firmware    e.g. LuatOS-Air_V4021_RDA8910_TTS_NOVOLTE_FLOAT
 *   ModemPdp -> modem_pdp         e.g. IPV4V6/redone (PDP type / APN)
 *
 * Why: 21 square-module boards on VoicePing SIMs sat on 301 for weeks and the only way to see
 * why was a remote log pull; VoicePing asks for IPv4 PDP and points at a Luat firmware DNS fix.
 * NULL everywhere = a build that does not report (pre-307, or no square module).
 * ota_modem_changed_at = when one of these last CHANGED (writes are skipped when nothing
 * moved, so it is not a "last heard" time - is_online / last_updated_at are).
 *
 * Shipped on its own, before the code that writes it (the P path is hot; see the
 * deploy-gap note). The writer also checks the columns exist before writing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->unsignedSmallInteger('ota_fail_streak')->nullable();
            $table->string('ota_last_error', 160)->nullable();
            $table->string('modem_firmware', 64)->nullable();
            $table->string('modem_pdp', 64)->nullable();
            $table->timestamp('ota_modem_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'ota_fail_streak',
                'ota_last_error',
                'modem_firmware',
                'modem_pdp',
                'ota_modem_changed_at',
            ]);
        });
    }
};
