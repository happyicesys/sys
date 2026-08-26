<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data-usage counters change unit: decimal MB → decimal KB (1000 bytes).
 *
 * The 2026_08_25_1200xx pair shipped these columns as MB and has already run
 * everywhere (batch 437), so the unit change CANNOT be made by editing those
 * files — Laravel would never re-run them and the live schema would keep the
 * _mb names while the code asked for _kb. This is that rename.
 *
 * WHY KB. The smart freezer's audited budget is ~330 KB/day; at MB resolution
 * a day's usage rounds to 0 and the daily snapshot diffs are all zeroes. KB
 * keeps a freezer-class budget visible. Matches the APK-side DataUsageLedger
 * (mark1-apk v303+), whose wire keys moved the same way:
 *
 *   "DataKB":1843201        all apps, all interfaces (rx+tx)
 *   "DataMobileKB":1790854  cellular interface only — the SIM-plan figure
 *   "DataAppKB":211077      the APK's own uid
 *   "DataDays":38           whole days the ledger has been metering
 *
 * NO VALUE CONVERSION. At write time the only populated row was bench rig 2031
 * (vends.id 1357) with every counter at 0, and vend_data_usage_snapshots was
 * empty — v303 has not been OTA'd, so no real MB figure exists to rescale. The
 * zeroes are cleared anyway so nothing can later be misread as a KB reading:
 * the APK re-reports within one heartbeat.
 */
return new class extends Migration
{
    /**
     * Old name => new name. Same list drives up() and down().
     */
    private const RENAMES = [
        'internet_data_mb' => 'internet_data_kb',
        'internet_data_mobile_mb' => 'internet_data_mobile_kb',
        'internet_data_app_mb' => 'internet_data_app_kb',
    ];

    private const SNAPSHOT_RENAMES = [
        'total_mb' => 'total_kb',
        'mobile_mb' => 'mobile_kb',
        'app_mb' => 'app_kb',
    ];

    public function up(): void
    {
        $this->rename('vends', self::RENAMES);
        $this->rename('vend_data_usage_snapshots', self::SNAPSHOT_RENAMES);

        // Stale MB figures would read as absurdly small KB ones. Nothing
        // downstream has consumed them yet (no snapshot rows existed), and the
        // APK re-reports its cumulative total on the next VENDER packet.
        if (Schema::hasColumn('vends', 'internet_data_kb')) {
            DB::table('vends')
                ->whereNotNull('internet_data_kb')
                ->update([
                    'internet_data_kb' => null,
                    'internet_data_mobile_kb' => null,
                    'internet_data_app_kb' => null,
                    'internet_data_days' => null,
                    'internet_data_updated_at' => null,
                ]);
        }

        DB::table('vend_data_usage_snapshots')->delete();
    }

    public function down(): void
    {
        $this->rename('vends', array_flip(self::RENAMES));
        $this->rename('vend_data_usage_snapshots', array_flip(self::SNAPSHOT_RENAMES));
    }

    /**
     * Rename only what is actually there, so a database that has already been
     * built from a later baseline (or half-migrated by hand) is not a hard
     * failure.
     *
     * @param  array<string, string>  $renames  old name => new name
     */
    private function rename(string $table, array $renames): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $renames) {
            foreach ($renames as $from => $to) {
                if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
                    $blueprint->renameColumn($from, $to);
                }
            }
        });
    }
};
