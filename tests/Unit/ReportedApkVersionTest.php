<?php

namespace Tests\Unit;

use App\ValueObjects\ReportedApkVersion;
use PHPUnit\Framework\TestCase;

/**
 * The two-channel version rule. A vending board reports through the PWRON
 * frame (apk_ver_json), a smart freezer only through the OTA check-in
 * (apk_version_code) — every reader must merge both or the freezer looks like
 * it has never reported.
 */
class ReportedApkVersionTest extends TestCase
{
    public function test_frame_only_machine_reports_its_json_version_with_build_time(): void
    {
        $version = ReportedApkVersion::fromAttributes(null, [
            'apkver' => 303,
            'buildtime' => '2026-08-30 12:00:00',
            'deviceType' => 'ZC-328',
        ]);

        $this->assertSame(303, $version->code);
        $this->assertSame(ReportedApkVersion::SOURCE_FRAME, $version->source);
        $this->assertSame('2026-08-30 12:00:00', $version->buildTime);
        $this->assertSame('ZC-328', $version->deviceType);
        $this->assertFalse($version->isOtaOnly());
    }

    public function test_ota_only_machine_reports_the_column_version(): void
    {
        // A smart freezer: apk_ver_json is NULL forever.
        $version = ReportedApkVersion::fromAttributes(12, null, '2026-09-16 09:12:03');

        $this->assertSame(12, $version->code);
        $this->assertSame(ReportedApkVersion::SOURCE_OTA, $version->source);
        $this->assertNull($version->buildTime);
        $this->assertTrue($version->isOtaOnly());
        $this->assertSame('2026-09-16 09:12:03', $version->checkedInAt);
    }

    public function test_the_higher_of_the_two_channels_wins(): void
    {
        // OTA'd after the last PWRON.
        $this->assertSame(305, ReportedApkVersion::fromAttributes(305, ['apkver' => 303])->code);
        $this->assertSame(
            ReportedApkVersion::SOURCE_OTA,
            ReportedApkVersion::fromAttributes(305, ['apkver' => 303])->source
        );

        // Rebooted after the last OTA check-in.
        $this->assertSame(305, ReportedApkVersion::fromAttributes(303, ['apkver' => 305])->code);
        $this->assertSame(
            ReportedApkVersion::SOURCE_FRAME,
            ReportedApkVersion::fromAttributes(303, ['apkver' => 305])->source
        );
    }

    public function test_a_tie_goes_to_the_frame_because_it_carries_the_build_time(): void
    {
        $version = ReportedApkVersion::fromAttributes(303, ['apkver' => 303, 'buildtime' => '2026-08-30 12:00:00']);

        $this->assertSame(ReportedApkVersion::SOURCE_FRAME, $version->source);
        $this->assertSame('2026-08-30 12:00:00', $version->buildTime);
    }

    public function test_never_reported_is_zero_with_no_source(): void
    {
        $version = ReportedApkVersion::fromAttributes(null, null);

        $this->assertSame(0, $version->code);
        $this->assertNull($version->source);
        $this->assertFalse($version->isReported());
    }

    public function test_it_reads_every_row_shape_the_app_produces(): void
    {
        // Query-builder row: raw JSON string. Eloquent row: cast array.
        // Already-decoded row: stdClass. Garbage: healed, not fatal.
        $this->assertSame(303, ReportedApkVersion::fromAttributes(null, '{"apkver":303}')->code);
        $this->assertSame(303, ReportedApkVersion::fromAttributes(null, (object) ['apkver' => 303])->code);
        $this->assertSame(303, ReportedApkVersion::fromAttributes(null, ['apkver' => '303'])->code);
        $this->assertSame(0, ReportedApkVersion::fromAttributes(null, 'not json')->code);
        $this->assertSame(0, ReportedApkVersion::fromAttributes('', ['apkver' => null])->code);
    }

    public function test_from_row_reads_the_three_columns_off_any_object(): void
    {
        $row = (object) [
            'apk_version_code' => 12,
            'apk_ver_json' => null,
            'apk_checked_in_at' => '2026-09-16 09:12:03',
        ];

        $this->assertSame(12, ReportedApkVersion::fromRow($row)->code);

        // A row that selected none of the three columns must not fatal.
        $this->assertSame(0, ReportedApkVersion::fromRow((object) ['code' => 2009])->code);
    }

    public function test_the_wire_payload_carries_the_provenance(): void
    {
        $this->assertSame([
            'code' => 12,
            'source' => ReportedApkVersion::SOURCE_OTA,
            'build_time' => null,
            'checked_in_at' => '2026-09-16 09:12:03',
        ], ReportedApkVersion::fromAttributes(12, null, '2026-09-16 09:12:03')->toArray());
    }
}
