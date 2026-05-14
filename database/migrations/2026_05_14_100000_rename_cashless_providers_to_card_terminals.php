<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Rename `cashless_providers` to `card_terminals`. The existing
     * `cashless_terminals` table still has a `cashless_provider_id` column
     * which now logically points to `card_terminals`; we deliberately leave
     * that column name unchanged to avoid an extra column-rename migration
     * (the cashless_terminals table is being deprecated / truncated as part
     * of the same migration plan, so the cosmetic mismatch doesn't matter).
     */
    public function up(): void
    {
        Schema::rename('cashless_providers', 'card_terminals');
    }

    public function down(): void
    {
        Schema::rename('card_terminals', 'cashless_providers');
    }
};
