<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card-terminal unit → machine binding (NETS TID etc.).
 *
 * Card sales reach mark1 only as APK TRADE frames, which carry no acquirer
 * reference of any kind — the settlement report identifies a sale by its
 * Terminal ID + timestamp + amount. This table is the missing link: which
 * physical terminal (the acquirer's 8-digit TID) sat on which vend, and when.
 *
 * Effective-dated (bound_from/bound_until, both nullable = open-ended) because
 * terminals do move between machines; settlement matching resolves the binding
 * as of each report row's transaction date, so historical reports still match
 * after a terminal is re-deployed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_terminal_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('nets');
            $table->string('terminal_id', 64);
            $table->unsignedBigInteger('vend_id');
            $table->date('bound_from')->nullable();
            $table->date('bound_until')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['provider', 'terminal_id']);
            $table->index('vend_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_terminal_bindings');
    }
};
