<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CityBox added an undocumented `status` field to `product_list` some time
 * after their 2026-08-14 spec (confirmed live 2026-09-05). It is the
 * enabled/disabled flag their OPS Pro portal shows, and it is the ONLY
 * signal that a SKU has been retired — their catalog keeps returning
 * disabled rows forever, so absence-based delisting never fires for them.
 *
 * Stored raw (their number, not a boolean) so an unrecognised value is
 * visible rather than silently coerced: 0 = disabled, 1 = enabled, and 99
 * seen once on a duplicate row — meaning unconfirmed, treated as not-enabled.
 * NULL = not yet seen on a catalog run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citybox_products', function (Blueprint $table) {
            $table->unsignedTinyInteger('citybox_status')->nullable()->after('is_delisted')->index();
            $table->timestamp('citybox_status_at')->nullable()->after('citybox_status');
        });
    }

    public function down(): void
    {
        Schema::table('citybox_products', function (Blueprint $table) {
            $table->dropColumn(['citybox_status', 'citybox_status_at']);
        });
    }
};
