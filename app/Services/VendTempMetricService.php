<?php

namespace App\Services;

use App\Models\VendTemp;
use App\Models\VendTempMetric;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VendTempMetricService
{
    /**
     * Compute and persist daily metrics for a specific calendar date.
     *
     * @return Collection<int, array{vend_id:int,temp_type:int,min_value:int,max_value:int,reading_count:int,min_recorded_at:?Carbon,max_recorded_at:?Carbon}>
     */
    public function computeDailyMetrics(Carbon $date, ?int $vendId = null): Collection
    {
        $query = VendTemp::query()
            ->selectRaw('vend_id, type as temp_type, MIN(value) as min_value, MAX(value) as max_value, COUNT(*) as reading_count')
            ->whereDate('created_at', $date)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR);

        if ($vendId) {
            $query->where('vend_id', $vendId);
        }

        $rows = $query->groupBy('vend_id', 'temp_type')->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $results = collect();

        foreach ($rows as $row) {
            $payload = [
                'vend_id' => (int) $row->vend_id,
                'temp_type' => (int) $row->temp_type,
                'min_value' => (int) $row->min_value,
                'max_value' => (int) $row->max_value,
                'reading_count' => (int) $row->reading_count,
            ];

            $extremes = $this->resolveDailyExtremes(
                $date,
                $payload['vend_id'],
                $payload['temp_type'],
                $payload['min_value'],
                $payload['max_value']
            );

            $payload['min_recorded_at'] = $extremes['min'];
            $payload['max_recorded_at'] = $extremes['max'];

            $this->storeDailyMetric($date, $payload);
            $this->refreshSummaryMetrics($date, $payload['vend_id'], $payload['temp_type']);

            $results->push($payload);
        }

        return $results;
    }

    private function storeDailyMetric(Carbon $date, array $payload): void
    {
        VendTempMetric::query()->updateOrCreate(
            [
                'vend_id' => $payload['vend_id'],
                'temp_type' => $payload['temp_type'],
                'period_type' => VendTempMetric::PERIOD_DAILY,
                'period_key' => $date->toDateString(),
            ],
            [
                'period_start' => $date->copy(),
                'period_end' => $date->copy(),
                'min_temp_value' => $payload['min_value'],
                'max_temp_value' => $payload['max_value'],
                'reading_count' => $payload['reading_count'],
                'days_covered' => 1,
                'min_temp_recorded_at' => $payload['min_recorded_at'],
                'max_temp_recorded_at' => $payload['max_recorded_at'],
                'computed_at' => now(),
            ]
        );
    }

    private function refreshSummaryMetrics(Carbon $endDate, int $vendId, int $tempType): void
    {
        $this->refreshAllTime($vendId, $tempType);
        $this->refreshRolling($endDate, $vendId, $tempType, 30, VendTempMetric::PERIOD_ROLLING_30);
        $this->refreshRolling($endDate, $vendId, $tempType, 60, VendTempMetric::PERIOD_ROLLING_60);
        $this->refreshRolling($endDate, $vendId, $tempType, 90, VendTempMetric::PERIOD_ROLLING_90);
    }

    private function refreshAllTime(int $vendId, int $tempType): void
    {
        $aggregate = VendTempMetric::query()
            ->selectRaw('MIN(min_temp_value) as min_value, MAX(max_temp_value) as max_value, SUM(reading_count) as reading_count, COUNT(*) as days_covered, MIN(period_start) as start_date, MAX(period_end) as end_date')
            ->where('vend_id', $vendId)
            ->where('temp_type', $tempType)
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->first();

        if (!$aggregate || is_null($aggregate->min_value) || is_null($aggregate->max_value)) {
            VendTempMetric::query()
                ->where('vend_id', $vendId)
                ->where('temp_type', $tempType)
                ->where('period_type', VendTempMetric::PERIOD_ALL_TIME)
                ->delete();

            return;
        }

        $minRow = VendTempMetric::query()
            ->where('vend_id', $vendId)
            ->where('temp_type', $tempType)
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereNotNull('min_temp_value')
            ->orderBy('min_temp_value')
            ->orderBy('min_temp_recorded_at')
            ->first();

        $maxRow = VendTempMetric::query()
            ->where('vend_id', $vendId)
            ->where('temp_type', $tempType)
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereNotNull('max_temp_value')
            ->orderByDesc('max_temp_value')
            ->orderBy('max_temp_recorded_at')
            ->first();

        if (!$minRow || !$maxRow) {
            VendTempMetric::query()
                ->where('vend_id', $vendId)
                ->where('temp_type', $tempType)
                ->where('period_type', VendTempMetric::PERIOD_ALL_TIME)
                ->delete();

            return;
        }

        VendTempMetric::query()->updateOrCreate(
            [
                'vend_id' => $vendId,
                'temp_type' => $tempType,
                'period_type' => VendTempMetric::PERIOD_ALL_TIME,
                'period_key' => 'all_time',
            ],
            [
                'period_start' => $aggregate->start_date,
                'period_end' => $aggregate->end_date,
                'min_temp_value' => (int) $minRow->min_temp_value,
                'max_temp_value' => (int) $maxRow->max_temp_value,
                'reading_count' => (int) $aggregate->reading_count,
                'days_covered' => (int) $aggregate->days_covered,
                'min_temp_recorded_at' => $minRow->min_temp_recorded_at,
                'max_temp_recorded_at' => $maxRow->max_temp_recorded_at,
                'computed_at' => now(),
            ]
        );
    }

    private function refreshRolling(Carbon $endDate, int $vendId, int $tempType, int $windowDays, string $periodType): void
    {
        $startDate = $endDate->copy()->subDays($windowDays - 1);

        $aggregate = VendTempMetric::query()
            ->selectRaw('MIN(min_temp_value) as min_value, MAX(max_temp_value) as max_value, SUM(reading_count) as reading_count, COUNT(*) as days_covered')
            ->where('vend_id', $vendId)
            ->where('temp_type', $tempType)
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereBetween('period_start', [$startDate, $endDate])
            ->first();

        if (!$aggregate || is_null($aggregate->min_value) || is_null($aggregate->max_value)) {
            VendTempMetric::query()
                ->where('vend_id', $vendId)
                ->where('temp_type', $tempType)
                ->where('period_type', $periodType)
                ->where('period_key', $this->rollingPeriodKey($periodType, $endDate))
                ->delete();

            return;
        }

        $minRow = VendTempMetric::query()
            ->where('vend_id', $vendId)
            ->where('temp_type', $tempType)
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereBetween('period_start', [$startDate, $endDate])
            ->whereNotNull('min_temp_value')
            ->orderBy('min_temp_value')
            ->orderBy('min_temp_recorded_at')
            ->first();

        $maxRow = VendTempMetric::query()
            ->where('vend_id', $vendId)
            ->where('temp_type', $tempType)
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereBetween('period_start', [$startDate, $endDate])
            ->whereNotNull('max_temp_value')
            ->orderByDesc('max_temp_value')
            ->orderBy('max_temp_recorded_at')
            ->first();

        if (!$minRow || !$maxRow) {
            VendTempMetric::query()
                ->where('vend_id', $vendId)
                ->where('temp_type', $tempType)
                ->where('period_type', $periodType)
                ->where('period_key', $this->rollingPeriodKey($periodType, $endDate))
                ->delete();

            return;
        }

        VendTempMetric::query()->updateOrCreate(
            [
                'vend_id' => $vendId,
                'temp_type' => $tempType,
                'period_type' => $periodType,
                'period_key' => $this->rollingPeriodKey($periodType, $endDate),
            ],
            [
                'period_start' => $startDate,
                'period_end' => $endDate->copy(),
                'min_temp_value' => (int) $minRow->min_temp_value,
                'max_temp_value' => (int) $maxRow->max_temp_value,
                'reading_count' => (int) $aggregate->reading_count,
                'days_covered' => (int) $aggregate->days_covered,
                'min_temp_recorded_at' => $minRow->min_temp_recorded_at,
                'max_temp_recorded_at' => $maxRow->max_temp_recorded_at,
                'computed_at' => now(),
            ]
        );
    }

    private function rollingPeriodKey(string $periodType, Carbon $endDate): string
    {
        return sprintf('%s:%s', $periodType, $endDate->toDateString());
    }

    /**
     * Resolve the timestamps where min and max values occurred for the given day.
     *
     * @return array{min:?Carbon,max:?Carbon}
     */
    private function resolveDailyExtremes(Carbon $date, int $vendId, int $tempType, int $minValue, int $maxValue): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $minRecord = VendTemp::query()
            ->where('vend_id', $vendId)
            ->where('type', $tempType)
            ->whereBetween('created_at', [$start, $end])
            ->where('value', $minValue)
            ->orderBy('created_at')
            ->first();

        $maxRecord = VendTemp::query()
            ->where('vend_id', $vendId)
            ->where('type', $tempType)
            ->whereBetween('created_at', [$start, $end])
            ->where('value', $maxValue)
            ->orderBy('created_at')
            ->first();

        return [
            'min' => $minRecord?->created_at,
            'max' => $maxRecord?->created_at,
        ];
    }
}
