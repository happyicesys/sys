<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "VoicePing 6GB/y" SimCard Package (telcos.id 8 in prod) was created
 * through the UI after the 2026-08-27 backfill, and the UI has no
 * usage_provider field, so simcards:sync-usage skipped its 100 sims. Map every
 * VoicePing package that is still unmapped to the shared VoicePing usage API.
 *
 * Forward-only, same rule as 2026_08_27_000000: telcos named VoicePing* use
 * the 'voiceping' provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('telcos')
            ->where('name', 'LIKE', 'VoicePing%')
            ->whereNull('usage_provider')
            ->update(['usage_provider' => 'voiceping']);
    }

    public function down(): void
    {
        // Forward-only: unmapping would silently stop the status sync.
    }
};
