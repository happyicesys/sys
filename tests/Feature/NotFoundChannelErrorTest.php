<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\VendChannelError;
use App\Support\DispenseVerdict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 2 of NA_ERROR_CODE_PLAN_2026-09-08.md: the reference row for code 99
 * comes from a migration, the marker's watermark column exists, and 99 reads
 * "NA" wherever a human sees a code.
 */
class NotFoundChannelErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_seeds_exactly_one_not_found_row(): void
    {
        $rows = VendChannelError::where('code', DispenseVerdict::NOT_FOUND_CODE)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Machine transaction not found (NA)', $rows->first()->desc);
        $this->assertSame(0, (int) $rows->first()->weightage);
        $this->assertSame(VendChannelError::CODE_NOT_FOUND, $rows->first()->code);
    }

    public function test_the_marker_watermark_column_exists_and_casts(): void
    {
        $this->assertTrue(Schema::hasColumn('settings', 'missing_trade_marked_until'));

        $setting = Setting::create(['missing_trade_marked_until' => '2026-09-08 00:00:00']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $setting->fresh()->missing_trade_marked_until);
    }

    public function test_display_code(): void
    {
        $this->assertSame('NA', DispenseVerdict::displayCode(99));
        $this->assertSame('NA', DispenseVerdict::displayCode('99'));
        $this->assertSame('7', DispenseVerdict::displayCode('07'));
        $this->assertSame('0', DispenseVerdict::displayCode(0));
        $this->assertSame('', DispenseVerdict::displayCode(null));
        $this->assertSame('', DispenseVerdict::displayCode(''));
        $this->assertSame('abc', DispenseVerdict::displayCode('abc'));
    }
}
