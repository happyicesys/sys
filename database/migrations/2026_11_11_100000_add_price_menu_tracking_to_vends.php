<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-11-11 (machine 10033, Indonesia): after a Site RP or a selling price changes, the machine page must
 * show the new Ref Price at once AND whether the machine has taken it. The machine's own fetch of its menu
 * (`/thumbnails`, which carries `server_price` per channel) is the proof: mark1 records when it was served and the
 * price it served for each channel, and when it last told the machine to re-read. Same idea as the payment menu
 * tracking of 2026-10-30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dateTime('price_menu_fetched_at')->nullable();
            $table->json('price_menu_prices_json')->nullable();
            $table->dateTime('price_menu_pushed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['price_menu_fetched_at', 'price_menu_prices_json', 'price_menu_pushed_at']);
        });
    }
};
