<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vends: last-known internet link reported by the machine's APK.
 *
 * From APK v302 (mark1-apk) / v134 (mark1-apk-small) the VENDER telemetry packet
 * carries one extra key describing how the machine is reaching the internet:
 *
 *   "Internet":{"Source":"telco","Provider":"Digi","Signal":4,"SignalMax":5,"Network":"4G"}
 *
 * The whole packet already lands in vends.parameter_json, so these columns are
 * NOT about storage - they exist so the vends list can read link quality
 * directly instead of json_extract-ing it out of every row, and so future
 * filtering, sorting or alerting has something indexable to hang off.
 * SyncVendParameter promotes the values on each packet. No index is added now:
 * nothing sorts or filters on these yet and the table is small - add one with
 * the query that needs it.
 *
 * Every column is additive and nullable, so:
 *   - existing rows are untouched and no existing query changes behaviour;
 *   - a machine still on an older APK simply never writes them and they stay
 *     NULL, which reads as "this machine has not told us" rather than as a
 *     bogus zero-bar reading.
 *
 *   internet_source      - telco | wifi | lan | other | none
 *   internet_provider    - carrier name, or the SSID when on Wi-Fi
 *   internet_signal      - bars, 0..internet_signal_max; NULL when unreadable
 *   internet_signal_max  - the scale the bars were measured on (currently 5)
 *   internet_network     - 2G / 3G / 4G / 5G / Cell; NULL off cellular
 *   internet_updated_at  - when the machine last reported any of the above
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->string('internet_source', 16)->nullable()->after('last_ip_address');
            $table->string('internet_provider', 64)->nullable()->after('internet_source');
            $table->unsignedTinyInteger('internet_signal')->nullable()->after('internet_provider');
            $table->unsignedTinyInteger('internet_signal_max')->nullable()->after('internet_signal');
            $table->string('internet_network', 16)->nullable()->after('internet_signal_max');
            $table->timestamp('internet_updated_at')->nullable()->after('internet_network');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'internet_source',
                'internet_provider',
                'internet_signal',
                'internet_signal_max',
                'internet_network',
                'internet_updated_at',
            ]);
        });
    }
};
