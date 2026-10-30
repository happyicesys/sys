<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-10-30: a change on Operator → Edit → Machine Payment Menu must reach the machines at once, and the
 * page must show which machines have it. Each machine's own fetch of its QR menu is the proof: mark1 records when
 * and what it served (a fingerprint of the exact reply), and when it last told the machine to re-read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dateTime('payment_menu_fetched_at')->nullable();
            $table->char('payment_menu_hash', 32)->nullable();
            $table->string('payment_menu_path', 64)->nullable();
            $table->dateTime('payment_menu_pushed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['payment_menu_fetched_at', 'payment_menu_hash', 'payment_menu_path', 'payment_menu_pushed_at']);
        });
    }
};
