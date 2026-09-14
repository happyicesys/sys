<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Smart Freezers must follow the Site's pricing (Vend::requiresServerPrice()).
 * 50001 and 50002 were created with is_using_server_price = 0, so mark1 sent
 * server_price = null and the freezer APK showed an empty menu. The model hook
 * covers every write from now on; this fixes the rows already stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vends')
            ->where('machine_type', 'smart_freezer')
            ->where('is_using_server_price', false)
            ->update(['is_using_server_price' => true]);
    }

    public function down(): void
    {
        // Forward-only data fix: the prior 0 was the defect.
    }
};
