<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-package usage API link. telcos.usage_provider names the provider class
 * (config/simcard_usage.php); telcos.usage_endpoint optionally overrides that
 * provider's default endpoint for this package only, so a reseller that serves
 * different carriers from different query links (VoicePing: Starhub via the CMI
 * sim-info API, Singtel elsewhere) is one provider class + one URL per package,
 * set from the SimCard Package form instead of a migration per package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telcos', function (Blueprint $table) {
            $table->string('usage_endpoint')->nullable()->after('usage_provider');
        });
    }

    public function down(): void
    {
        Schema::table('telcos', function (Blueprint $table) {
            $table->dropColumn('usage_endpoint');
        });
    }
};
