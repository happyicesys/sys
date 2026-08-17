<?php

namespace Tests\Feature;

use App\Models\Vend;
use App\Models\VendTemp;
use App\Models\VendTempMetric;
use App\Services\VendTempMetricService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VendTempMetricServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
    }

    /**
     * Regression: the daily aggregate must filter created_at with a plain datetime
     * range. whereDate() compiles to date(created_at) = ?, which wraps the column in
     * a function so vend_temps_created_at_index cannot be used — on prod that turned
     * this into a 21s full scan of ~12.6M rows (2.1GB), evicting the buffer pool and
     * slowing every concurrent page load.
     */
    public function test_daily_aggregate_does_not_wrap_created_at_in_a_function()
    {
        $vend = Vend::create(['code' => 'V100', 'name' => 'Range Predicate Vend']);

        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 120,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => Carbon::parse('2026-08-15 09:00:00'),
        ]);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        (new VendTempMetricService)->computeDailyMetrics(Carbon::parse('2026-08-15'));

        $aggregates = array_filter(
            $statements,
            fn ($sql) => str_contains($sql, 'from `vend_temps`') && str_contains($sql, 'reading_count')
        );

        $this->assertNotEmpty($aggregates, 'The daily aggregate query never ran.');

        foreach ($aggregates as $sql) {
            $this->assertStringNotContainsString(
                'date(`created_at`)',
                $sql,
                'created_at must not be wrapped in DATE() — it makes the index unusable.'
            );
            $this->assertStringContainsString('`created_at` between', $sql);
        }
    }

    /**
     * The rewritten predicate must still mean exactly "this calendar day": both
     * midnight boundaries inclusive of the day itself, and nothing either side.
     */
    public function test_daily_aggregate_covers_the_whole_day_and_nothing_outside_it()
    {
        $vend = Vend::create(['code' => 'V101', 'name' => 'Boundary Vend']);

        $readings = [
            ['2026-08-14 23:59:59', 500],  // previous day — excluded
            ['2026-08-15 00:00:00', 100],  // first instant — included, and the min
            ['2026-08-15 12:00:00', 150],
            ['2026-08-15 23:59:59', 200],  // last instant — included, and the max
            ['2026-08-16 00:00:00', 900],  // next day — excluded
        ];

        foreach ($readings as [$createdAt, $value]) {
            VendTemp::create([
                'vend_id' => $vend->id,
                'value' => $value,
                'type' => VendTemp::TYPE_CHAMBER,
                'created_at' => Carbon::parse($createdAt),
            ]);
        }

        $results = (new VendTempMetricService)->computeDailyMetrics(Carbon::parse('2026-08-15'));

        $this->assertCount(1, $results);

        $metric = $results->first();
        $this->assertSame(3, $metric['reading_count']);
        $this->assertSame(100, $metric['min_value']);
        $this->assertSame(200, $metric['max_value']);
        $this->assertSame('2026-08-15 00:00:00', $metric['min_recorded_at']->toDateTimeString());
        $this->assertSame('2026-08-15 23:59:59', $metric['max_recorded_at']->toDateTimeString());

        $this->assertDatabaseHas('vend_temp_metrics', [
            'vend_id' => $vend->id,
            'temp_type' => VendTemp::TYPE_CHAMBER,
            'period_type' => VendTempMetric::PERIOD_DAILY,
            'period_key' => '2026-08-15',
            'reading_count' => 3,
            'min_temp_value' => 100,
            'max_temp_value' => 200,
        ]);
    }

    public function test_it_excludes_sensor_error_readings()
    {
        $vend = Vend::create(['code' => 'V102', 'name' => 'Error Reading Vend']);

        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => VendTemp::TEMPERATURE_ERROR,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => Carbon::parse('2026-08-15 08:00:00'),
        ]);

        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 130,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => Carbon::parse('2026-08-15 09:00:00'),
        ]);

        $metric = (new VendTempMetricService)->computeDailyMetrics(Carbon::parse('2026-08-15'))->first();

        $this->assertSame(1, $metric['reading_count']);
        $this->assertSame(130, $metric['min_value']);
        $this->assertSame(130, $metric['max_value']);
    }

    /**
     * computeDailyMetrics() takes a mutable Carbon. Deriving the range must not
     * shift the caller's instance — BackfillVendTempMetrics loops over dates.
     */
    public function test_it_does_not_mutate_the_date_argument()
    {
        $date = Carbon::parse('2026-08-15 07:30:00');

        (new VendTempMetricService)->computeDailyMetrics($date);

        $this->assertSame('2026-08-15 07:30:00', $date->toDateTimeString());
    }
}
