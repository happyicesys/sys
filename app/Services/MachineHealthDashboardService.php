<?php

namespace App\Services;

use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendChannelStockEvent;
use App\Models\VendTemp;
use App\Models\VendTempMetric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MachineHealthDashboardService
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function getDashboardData(Request $request): array
    {
        $filters = $this->hydrateFilters($request);

        if (!$this->shouldCache($filters)) {
            return $this->buildDashboardPayload($filters);
        }

        $cacheKey = $this->buildCacheKey($filters);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($filters) {
            return $this->buildDashboardPayload($filters);
        });
    }

    private function buildDashboardPayload(array $filters): array
    {
        $stockoutMetrics = $this->getStockoutMetrics($filters);

        return [
            'filters' => $filters,
            'summary' => $stockoutMetrics['summary'],
            'error_codes' => $this->getErrorCodeMetrics($filters),
            'temperature' => $this->getTemperatureMetrics($filters),
            'connectivity' => $this->getConnectivityMetrics($filters),
            'no_transactions' => $this->getNoTransactionMetrics($filters),
            'stockouts' => $stockoutMetrics,
        ];
    }

    private function hydrateFilters(Request $request): array
    {
        return [
            'machine_limit' => $this->clamp((int) $request->input('machine_limit', 10), 10, 50),
            'channel_limit' => $this->clamp((int) $request->input('channel_limit', 10), 10, 50),
            'error_window_days' => $this->clamp((int) $request->input('error_window_days', 7), 1, 90),
            'temperature_window_days' => $this->clamp((int) $request->input('temperature_window_days', 7), 3, 90),
            'temperature_delta_threshold' => (float) $request->input('temperature_delta_threshold', 3),
            'temperature_min_threshold' => (float) $request->input('temperature_min_threshold', -18),
            'temperature_sensor_type' => (int) $request->input('temperature_sensor_type', VendTemp::TYPE_CHAMBER),
            'temperature_long_window_days' => $this->clamp((int) $request->input('temperature_long_window_days', 30), 7, 120),
            'no_txn_threshold_hours' => [
                'any' => (int) $request->input('no_txn_threshold_hours.any', 66),
                'cash' => (int) $request->input('no_txn_threshold_hours.cash', 72),
                'card' => (int) $request->input('no_txn_threshold_hours.card', 72),
                'cashless' => (int) $request->input('no_txn_threshold_hours.cashless', 72),
            ],
            'offline_threshold_hours' => (int) $request->input('offline_threshold_hours', 12),
            'offline_secondary_threshold_hours' => (int) $request->input('offline_secondary_threshold_hours', 24),
            'stockout_target_hours' => max(1, (int) $request->input('stockout_target_hours', 72)),
            'stockout_lookback_days' => $this->clamp((int) $request->input('stockout_lookback_days', 30), 7, 180),
            'vend_prefix_ids' => $this->normalizeIdArray($request->input('vend_prefix_ids')),
            'operator_ids' => $this->normalizeIdArray($request->input('operator_ids')),
            'customer_ids' => $this->normalizeIdArray($request->input('customer_ids')),
            'machine_codes' => $this->normalizeStringArray($request->input('machine_codes')),
            'channel_sku' => $request->input('channel_sku'),
        ];
    }

    private function shouldCache(array $filters): bool
    {
        return empty($filters['machine_codes'])
            && empty($filters['channel_sku'])
            && empty($filters['customer_ids'])
            && empty($filters['operator_ids'])
            && empty($filters['vend_prefix_ids']);
    }

    private function buildCacheKey(array $filters): string
    {
        $normalized = $this->normalizeForCache($filters);

        return 'machine-health:' . md5(json_encode($normalized));
    }

    private function normalizeForCache(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                if ($this->isSequentialArray($item)) {
                    $sorted = array_values($item);
                    sort($sorted);
                    $value[$key] = $sorted;
                } else {
                    $value[$key] = $this->normalizeForCache($item);
                }
            }
        }

        ksort($value);

        return $value;
    }

    private function isSequentialArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function getStockoutMetrics(array $filters): array
    {
        $lookbackStart = Carbon::now()->subDays($filters['stockout_lookback_days'])->startOfDay();

        $eventsQuery = VendChannelStockEvent::query()
            ->where('occurred_at', '>=', $lookbackStart)
            ->whereHas('vend', function (EloquentBuilder $query) use ($filters) {
                $this->applyVendFilters($query, $filters);
                $query->where('is_testing', false);
            })
            ->with([
                'vend:id,code,name,operator_id,vend_prefix_id,customer_id,is_testing',
                'vend.customer:id,name,code',
                'vend.operator:id,name,code',
                'vend.vendPrefix:id,name',
                'vendChannel:id,vend_id,code',
                'product:id,name,code',
            ])
            ->orderBy('vend_channel_id')
            ->orderBy('occurred_at');

        if ($filters['channel_sku']) {
            $eventsQuery->whereHas('vendChannel', function (EloquentBuilder $query) use ($filters) {
                $query->where('sku_code', $filters['channel_sku']);
            });
        }

        $events = $eventsQuery->get();

        if ($events->isEmpty()) {
            return [
                'summary' => $this->defaultStockoutSummary($filters['stockout_target_hours']),
                'top_channels' => [],
                'open_channels' => [],
                'trend' => [],
                'metadata' => [
                    'lookback_days' => $filters['stockout_lookback_days'],
                ],
            ];
        }

        $now = Carbon::now();
        $closedDurations = collect();
        $openDurations = collect();

        $events->groupBy('vend_channel_id')->each(function (Collection $channelEvents) use (&$closedDurations, &$openDurations, $now) {
            $sorted = $channelEvents->sortBy('occurred_at')->values();
            $pendingSoldOut = null;

            foreach ($sorted as $event) {
                if ($event->event_type === VendChannelStockEvent::TYPE_SOLD_OUT) {
                    $pendingSoldOut = $event;
                    continue;
                }

                if ($event->event_type === VendChannelStockEvent::TYPE_RESTOCKED && $pendingSoldOut) {
                    $durationMinutes = $pendingSoldOut->occurred_at->diffInMinutes($event->occurred_at);
                    $closedDurations->push($this->formatStockoutDuration($pendingSoldOut, $event, $durationMinutes));
                    $pendingSoldOut = null;
                }
            }

            if ($pendingSoldOut) {
                $durationMinutes = $pendingSoldOut->occurred_at->diffInMinutes($now);
                $openDurations->push($this->formatStockoutDuration($pendingSoldOut, null, $durationMinutes));
            }
        });

        $summary = $this->buildStockoutSummary($closedDurations, $filters['stockout_target_hours']);

        $topChannels = $openDurations
            ->merge($closedDurations)
            ->sortByDesc('duration_hours')
            ->take($filters['channel_limit'])
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'top_channels' => $topChannels,
            'open_channels' => $openDurations->sortByDesc('duration_hours')->values()->all(),
            'trend' => $this->buildStockoutTrend($closedDurations),
            'metadata' => [
                'lookback_days' => $filters['stockout_lookback_days'],
            ],
        ];
    }

    private function defaultStockoutSummary(int $targetHours): array
    {
        return [
            'average_duration_hours' => null,
            'average_duration_days' => null,
            'longest_duration_hours' => null,
            'longest_duration_days' => null,
            'recovery_rate' => null,
            'closed_events_count' => 0,
            'target_hours' => $targetHours,
            'target_days' => round($targetHours / 24, 2),
        ];
    }

    private function buildStockoutSummary(Collection $durations, int $targetHours): array
    {
        if ($durations->isEmpty()) {
            return $this->defaultStockoutSummary($targetHours);
        }

        $avgHours = $durations->avg('duration_hours');
        $maxHours = $durations->max('duration_hours');
        $withinTarget = $durations->filter(fn ($item) => $item['duration_hours'] <= $targetHours)->count();

        return [
            'average_duration_hours' => round($avgHours, 2),
            'average_duration_days' => round($avgHours / 24, 2),
            'longest_duration_hours' => round($maxHours, 2),
            'longest_duration_days' => round($maxHours / 24, 2),
            'recovery_rate' => $durations->count() > 0 ? round(($withinTarget / $durations->count()) * 100, 2) : null,
            'closed_events_count' => $durations->count(),
            'target_hours' => $targetHours,
            'target_days' => round($targetHours / 24, 2),
        ];
    }

    private function buildStockoutTrend(Collection $durations): array
    {
        if ($durations->isEmpty()) {
            return [];
        }

        return $durations
            ->groupBy(function ($item) {
                return Carbon::parse($item['stockout_at'])->startOfWeek()->toDateString();
            })
            ->map(function (Collection $weekBuckets, string $weekStart) {
                $average = $weekBuckets->avg('duration_hours');

                return [
                    'week_start' => $weekStart,
                    'average_hours' => round($average, 2),
                    'average_days' => round($average / 24, 2),
                    'sample_size' => $weekBuckets->count(),
                ];
            })
            ->sortBy('week_start')
            ->values()
            ->all();
    }

    private function formatStockoutDuration(VendChannelStockEvent $soldOut, ?VendChannelStockEvent $restocked, int $durationMinutes): array
    {
        $vend = $soldOut->vend;
        $channel = $soldOut->vendChannel;
        $product = $soldOut->product ?? $channel?->product;

        $durationHours = round($durationMinutes / 60, 2);

        return [
            'vend_id' => $vend?->id,
            'vend_code' => $vend?->code,
            'vend_name' => $vend?->name,
            'customer_name' => $vend?->customer?->name,
            'operator_name' => $vend?->operator?->name,
            'vend_prefix_name' => $vend?->vendPrefix?->name,
            'channel_code' => $channel?->code,
            'product_name' => $product?->name,
            'duration_hours' => $durationHours,
            'duration_days' => round($durationHours / 24, 2),
            'stockout_at' => optional($soldOut->occurred_at)->toIso8601String(),
            'restocked_at' => optional($restocked?->occurred_at)->toIso8601String(),
            'is_open' => $restocked === null,
        ];
    }

    private function getErrorCodeMetrics(array $filters): array
    {
        $limit = $filters['machine_limit'];
        $windowDays = $filters['error_window_days'];
        $periodEnd = Carbon::now();
        $periodStart = $periodEnd->copy()->subDays($windowDays)->startOfDay();

        $codes = VendChannelError::query()
            ->whereIn('code', [3, 4, 5, 6, 7, 9])
            ->pluck('id', 'code');

        $groupDefinitions = [
            'dispense_stability' => [
                'label' => 'Dispense Stability (Error 7 & 9)',
                'codes' => [7, 9],
            ],
            'mechanical_handling' => [
                'label' => 'Motor & Mechanical (Error 3, 4, 5, 6)',
                'codes' => [3, 4, 5, 6],
            ],
        ];

        $results = [];

        foreach ($groupDefinitions as $key => $definition) {
            $errorIds = $codes->only($definition['codes'])->values()->all();

            if (empty($errorIds)) {
                $results[$key] = [
                    'label' => $definition['label'],
                    'window_days' => $windowDays,
                    'limit' => $limit,
                    'rows' => [],
                    'codes' => $definition['codes'],
                ];
                continue;
            }

            $query = VendChannelErrorLog::query()
                ->join('vend_channel_errors', 'vend_channel_error_logs.vend_channel_error_id', '=', 'vend_channel_errors.id')
                ->join('vend_channels', 'vend_channel_error_logs.vend_channel_id', '=', 'vend_channels.id')
                ->join('vends', 'vend_channels.vend_id', '=', 'vends.id')
                ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
                ->leftJoin('operators', 'vends.operator_id', '=', 'operators.id')
                ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
                ->whereBetween('vend_channel_error_logs.created_at', [$periodStart, $periodEnd])
                ->whereIn('vend_channel_error_logs.vend_channel_error_id', $errorIds)
                ->where('vends.is_testing', false);

            $this->applyVendFilters($query, $filters, 'vends');

            if ($filters['channel_sku']) {
                $sku = $filters['channel_sku'];
                $query->where(function ($subQuery) use ($sku) {
                    $subQuery->where('vend_channels.sku_code', $sku)
                        ->orWhere('vend_channels.sku_code', 'LIKE', "{$sku}%");
                });
            }

            $selects = [
                'vends.id as vend_id',
                'vends.code as vend_code',
                'vends.name as vend_name',
                'customers.name as customer_name',
                'operators.name as operator_name',
                'vend_prefixes.name as vend_prefix_name',
                DB::raw('COUNT(*) as total_events'),
                DB::raw('MAX(vend_channel_error_logs.created_at) as last_event_at'),
            ];

            foreach ($definition['codes'] as $code) {
                $selects[] = DB::raw("SUM(CASE WHEN vend_channel_errors.code = {$code} THEN 1 ELSE 0 END) as code_{$code}_count");
            }

            $rows = $query
                ->select($selects)
                ->groupBy('vends.id', 'vends.code', 'vends.name', 'customers.name', 'operators.name', 'vend_prefixes.name')
                ->orderByDesc('total_events')
                ->limit($limit)
                ->get();

            $results[$key] = [
                'label' => $definition['label'],
                'window_days' => $windowDays,
                'limit' => $limit,
                'codes' => $definition['codes'],
                'rows' => $rows->map(function ($row) use ($definition) {
                    $perCode = collect($definition['codes'])->map(function ($code) use ($row) {
                        return [
                            'code' => $code,
                            'count' => (int) ($row->{'code_' . $code . '_count'} ?? 0),
                        ];
                    })->values()->all();

                    return [
                        'vend_id' => (int) $row->vend_id,
                        'vend_code' => $row->vend_code,
                        'vend_name' => $row->vend_name,
                        'customer_name' => $row->customer_name,
                        'operator_name' => $row->operator_name,
                        'vend_prefix_name' => $row->vend_prefix_name,
                        'total_events' => (int) $row->total_events,
                        'last_event_at' => $row->last_event_at ? Carbon::parse($row->last_event_at)->toIso8601String() : null,
                        'per_code' => $perCode,
                    ];
                })->all(),
            ];
        }

        return $results;
    }

    private function getTemperatureMetrics(array $filters): array
    {
        $sensorType = $filters['temperature_sensor_type'];
        $shortWindow = $filters['temperature_window_days'];
        $longWindow = $filters['temperature_long_window_days'];
        $deltaThresholdRaw = $filters['temperature_delta_threshold'];
        $minThresholdRaw = (float) $filters['temperature_min_threshold'];

        $scale = 10;
        $deltaThresholdScaled = (int) round($deltaThresholdRaw * $scale);
        $minThresholdScaled = (int) round($minThresholdRaw * $scale);

        $now = Carbon::now();
        $shortStart = $now->copy()->subDays($shortWindow - 1)->startOfDay();
        $longStart = $now->copy()->subDays($longWindow - 1)->startOfDay();

        $dailyMetrics = VendTempMetric::query()
            ->with([
                'vend:id,code,name,operator_id,vend_prefix_id,customer_id,is_testing',
                'vend.customer:id,name,code',
                'vend.operator:id,name,code',
                'vend.vendPrefix:id,name',
            ])
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->where('temp_type', $sensorType)
            ->whereBetween('period_start', [$shortStart, $now])
            ->whereHas('vend', function (EloquentBuilder $query) use ($filters) {
                $this->applyVendFilters($query, $filters);
                $query->where('is_testing', false);
            })
            ->orderBy('vend_id')
            ->orderBy('period_start')
            ->get();

        $rising = $dailyMetrics->groupBy('vend_id')->map(function (Collection $metrics) use ($deltaThresholdScaled, $scale) {
            $usable = $metrics->filter(fn ($metric) => $metric->min_temp_value !== null)->sortBy('period_start')->values();

            if ($usable->count() < 2) {
                return null;
            }

            $first = $usable->first();
            $last = $usable->last();
            $delta = (int) $last->min_temp_value - (int) $first->min_temp_value;

            if ($delta < $deltaThresholdScaled || $last->min_temp_value <= $first->min_temp_value) {
                return null;
            }

            $vend = $first->vend;

            return [
                'vend_id' => $vend?->id,
                'vend_code' => $vend?->code,
                'vend_name' => $vend?->name,
                'customer_name' => $vend?->customer?->name,
                'operator_name' => $vend?->operator?->name,
                'vend_prefix_name' => $vend?->vendPrefix?->name,
                'first_min_temp' => round(((int) $first->min_temp_value) / $scale, 1),
                'first_day' => optional($first->period_start)->toDateString(),
                'latest_min_temp' => round(((int) $last->min_temp_value) / $scale, 1),
                'latest_day' => optional($last->period_start)->toDateString(),
                'delta' => round($delta / $scale, 2),
            ];
        })->filter()->sortByDesc('delta')->take($filters['machine_limit'])->values()->all();

        $worstMinimaQuery = VendTempMetric::query()
            ->from('vend_temp_metrics')
            ->join('vends', 'vend_temp_metrics.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('operators', 'vends.operator_id', '=', 'operators.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
            ->where('vend_temp_metrics.period_type', VendTempMetric::PERIOD_DAILY)
            ->where('vend_temp_metrics.temp_type', $sensorType)
            ->whereBetween('vend_temp_metrics.period_start', [$longStart, $now])
            ->where('vends.is_testing', false);

        $this->applyVendFilters($worstMinimaQuery, $filters, 'vends');

        $worstMinimaRows = $worstMinimaQuery
            ->select([
                'vend_temp_metrics.vend_id',
                'vends.code as vend_code',
                'vends.name as vend_name',
                'customers.name as customer_name',
                'operators.name as operator_name',
                'vend_prefixes.name as vend_prefix_name',
                DB::raw('MAX(vend_temp_metrics.min_temp_value) as worst_min_temp'),
                DB::raw('MIN(vend_temp_metrics.min_temp_value) as best_min_temp'),
                DB::raw('MAX(vend_temp_metrics.min_temp_recorded_at) as last_recorded_at'),
            ])
            ->groupBy('vend_temp_metrics.vend_id', 'vends.code', 'vends.name', 'customers.name', 'operators.name', 'vend_prefixes.name')
            ->havingRaw('MAX(vend_temp_metrics.min_temp_value) IS NOT NULL')
            ->orderByDesc(DB::raw('MAX(vend_temp_metrics.min_temp_value)'))
            ->limit($filters['machine_limit'])
            ->get()
            ->map(function ($row) use ($scale) {
                return [
                    'vend_id' => (int) $row->vend_id,
                    'vend_code' => $row->vend_code,
                    'vend_name' => $row->vend_name,
                    'customer_name' => $row->customer_name,
                    'operator_name' => $row->operator_name,
                    'vend_prefix_name' => $row->vend_prefix_name,
                    'worst_min_temp' => $row->worst_min_temp !== null ? round(((int) $row->worst_min_temp) / $scale, 1) : null,
                    'best_min_temp' => $row->best_min_temp !== null ? round(((int) $row->best_min_temp) / $scale, 1) : null,
                    'last_recorded_at' => $row->last_recorded_at ? Carbon::parse($row->last_recorded_at)->toIso8601String() : null,
                ];
            })
            ->all();

        $noReachQuery = VendTemp::query()
            ->from('vend_temps')
            ->join('vends', 'vend_temps.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('operators', 'vends.operator_id', '=', 'operators.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
            ->where('vend_temps.type', $sensorType)
            ->where('vend_temps.value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->where('vend_temps.created_at', '>=', $now->copy()->subHours(12))
            ->where('vends.is_temp_active', true)
            ->where('vends.is_testing', false);

        $this->applyVendFilters($noReachQuery, $filters, 'vends');

        $notReaching = $noReachQuery
            ->select([
                'vends.id as vend_id',
                'vends.code as vend_code',
                'vends.name as vend_name',
                'customers.name as customer_name',
                'operators.name as operator_name',
                'vend_prefixes.name as vend_prefix_name',
                DB::raw('MIN(vend_temps.value) as min_value'),
                DB::raw('MAX(vend_temps.created_at) as last_recorded_at'),
                DB::raw('COUNT(*) as reading_count'),
            ])
            ->groupBy('vends.id', 'vends.code', 'vends.name', 'customers.name', 'operators.name', 'vend_prefixes.name')
            ->havingRaw('MIN(vend_temps.value) > ?', [$minThresholdScaled])
            ->orderByDesc(DB::raw('MIN(vend_temps.value)'))
            ->limit($filters['machine_limit'])
            ->get()
            ->map(function ($row) use ($scale) {
                return [
                    'vend_id' => (int) $row->vend_id,
                    'vend_code' => $row->vend_code,
                    'vend_name' => $row->vend_name,
                    'customer_name' => $row->customer_name,
                    'operator_name' => $row->operator_name,
                    'vend_prefix_name' => $row->vend_prefix_name,
                    'min_value' => $row->min_value !== null ? round(((int) $row->min_value) / $scale, 1) : null,
                    'last_recorded_at' => $row->last_recorded_at ? Carbon::parse($row->last_recorded_at)->toIso8601String() : null,
                    'reading_count' => (int) $row->reading_count,
                ];
            })
            ->all();

        return [
            'rising_lowest' => [
                'window_days' => $shortWindow,
                'delta_threshold' => $deltaThresholdRaw,
                'rows' => $rising,
            ],
            'worst_minima' => [
                'window_days' => $longWindow,
                'rows' => $worstMinimaRows,
            ],
            'not_reaching_threshold' => [
                'threshold' => $minThresholdRaw,
                'hours' => 12,
                'rows' => $notReaching,
            ],
        ];
    }

    private function getConnectivityMetrics(array $filters): array
    {
        $primaryThreshold = max(1, $filters['offline_threshold_hours']);
        $secondaryThreshold = max($primaryThreshold, $filters['offline_secondary_threshold_hours']);
        $now = Carbon::now();

        $vendsQuery = Vend::query()
            ->with([
                'customer:id,name,code',
                'operator:id,name,code',
                'vendPrefix:id,name',
            ])
            ->where('is_testing', false)
            ->where('is_online', false);

        $this->applyVendFilters($vendsQuery, $filters);

        if (!empty($filters['machine_codes'])) {
            $vendsQuery->whereIn('code', $filters['machine_codes']);
        }

        $vends = $vendsQuery
            ->select([
                'id',
                'code',
                'name',
                'is_online',
                'operator_id',
                'vend_prefix_id',
                'customer_id',
                'last_updated_at',
                'mqtt_last_updated_at',
                'last_vend_transaction_at',
                'offline_restart_count_datetime',
            ])
            ->get();

        $primaryList = collect();
        $secondaryList = collect();

        foreach ($vends as $vend) {
            if ($vend->is_online) {
                continue;
            }

            $lastContact = $this->resolveLastContactTimestamp($vend);
            if (!$lastContact) {
                continue;
            }

            $hoursOffline = round($lastContact->diffInMinutes($now) / 60, 2);

            if ($hoursOffline >= $primaryThreshold) {
                $entry = array_merge(
                    $this->baseVendInfo($vend),
                    [
                        'hours_offline' => $hoursOffline,
                        'last_contact_at' => $lastContact->toIso8601String(),
                    ]
                );

                $primaryList->push($entry);

                if ($hoursOffline >= $secondaryThreshold) {
                    $secondaryList->push($entry);
                }
            }
        }

        return [
            'primary_threshold_hours' => $primaryThreshold,
            'secondary_threshold_hours' => $secondaryThreshold,
            'primary' => $primaryList->sortBy('hours_offline')->values()->all(),
            'secondary' => $secondaryList->sortBy('hours_offline')->values()->all(),
        ];
    }

    private function getNoTransactionMetrics(array $filters): array
    {
        $thresholds = $filters['no_txn_threshold_hours'];
        $now = Carbon::now();

        $vendsQuery = Vend::query()
            ->with([
                'customer:id,name,code',
                'operator:id,name,code',
                'vendPrefix:id,name',
            ])
            ->where('is_testing', false);

        $this->applyVendFilters($vendsQuery, $filters);

        if (!empty($filters['machine_codes'])) {
            $vendsQuery->whereIn('code', $filters['machine_codes']);
        }

        $vends = $vendsQuery
            ->select([
                'id',
                'code',
                'name',
                'operator_id',
                'vend_prefix_id',
                'customer_id',
                'last_vend_transaction_at',
                'last_cash_vend_transaction_at',
                'last_card_vend_transaction_at',
                'last_cashless_vend_transaction_at',
            ])
            ->get();

        $anyList = collect();
        $cashList = collect();
        $cardList = collect();
        $cashlessList = collect();

        foreach ($vends as $vend) {
            $entryBase = $this->baseVendInfo($vend);

            $lastAny = $vend->last_vend_transaction_at;
            if ($lastAny && $this->hoursSince($lastAny, $now) >= $thresholds['any']) {
                $anyList->push(array_merge($entryBase, [
                    'hours_since' => $this->hoursSince($lastAny, $now),
                    'last_transaction_at' => $lastAny->toIso8601String(),
                    'threshold_hours' => $thresholds['any'],
                ]));
            }

            $lastCash = $vend->last_cash_vend_transaction_at;
            if ($lastCash && $this->hoursSince($lastCash, $now) >= $thresholds['cash']) {
                $cashList->push(array_merge($entryBase, [
                    'hours_since' => $this->hoursSince($lastCash, $now),
                    'last_transaction_at' => $lastCash->toIso8601String(),
                    'threshold_hours' => $thresholds['cash'],
                ]));
            }

            $lastCard = $vend->last_card_vend_transaction_at;
            if ($lastCard && $this->hoursSince($lastCard, $now) >= $thresholds['card']) {
                $cardList->push(array_merge($entryBase, [
                    'hours_since' => $this->hoursSince($lastCard, $now),
                    'last_transaction_at' => $lastCard->toIso8601String(),
                    'threshold_hours' => $thresholds['card'],
                ]));
            }

            $lastCashless = $vend->last_cashless_vend_transaction_at;
            if ($lastCashless && $this->hoursSince($lastCashless, $now) >= $thresholds['cashless']) {
                $cashlessList->push(array_merge($entryBase, [
                    'hours_since' => $this->hoursSince($lastCashless, $now),
                    'last_transaction_at' => $lastCashless->toIso8601String(),
                    'threshold_hours' => $thresholds['cashless'],
                ]));
            }
        }

        return [
            'thresholds' => $thresholds,
            'any_sales' => $anyList->sortByDesc('hours_since')->values()->all(),
            'cash_sales' => $cashList->sortByDesc('hours_since')->values()->all(),
            'card_sales' => $cardList->sortByDesc('hours_since')->values()->all(),
            'qr_sales' => $cashlessList->sortByDesc('hours_since')->values()->all(),
        ];
    }

    private function applyVendFilters($query, array $filters, string $table = 'vends'): void
    {
        if (!empty($filters['vend_prefix_ids'])) {
            $query->whereIn("{$table}.vend_prefix_id", $filters['vend_prefix_ids']);
        }

        if (!empty($filters['operator_ids'])) {
            $query->whereIn("{$table}.operator_id", $filters['operator_ids']);
        }

        if (!empty($filters['customer_ids'])) {
            $query->whereIn("{$table}.customer_id", $filters['customer_ids']);
        }

        if (!empty($filters['machine_codes'])) {
            $query->whereIn("{$table}.code", $filters['machine_codes']);
        }
    }

    private function resolveLastContactTimestamp(Vend $vend): ?Carbon
    {
        return collect([
            $vend->mqtt_last_updated_at,
            $vend->last_updated_at,
            $vend->last_vend_transaction_at,
            $vend->offline_restart_count_datetime,
        ])->filter()->sort()->last();
    }

    private function hoursSince(?Carbon $timestamp, Carbon $now): ?float
    {
        if (!$timestamp) {
            return null;
        }

        return round($timestamp->diffInMinutes($now) / 60, 2);
    }

    private function baseVendInfo(?Vend $vend): array
    {
        return [
            'vend_id' => $vend?->id,
            'vend_code' => $vend?->code,
            'vend_name' => $vend?->name,
            'customer_name' => $vend?->customer?->name,
            'operator_name' => $vend?->operator?->name,
            'vend_prefix_name' => $vend?->vendPrefix?->name,
        ];
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function normalizeIdArray($input): array
    {
        return array_values(array_filter(array_map('intval', $this->normalizeArray($input)), fn ($value) => $value > 0));
    }

    private function normalizeStringArray($input): array
    {
        return array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $this->normalizeArray($input)), fn ($value) => $value !== ''));
    }

    private function normalizeArray($input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_array($input)) {
            return $input;
        }

        if (is_string($input)) {
            return explode(',', $input);
        }

        return [$input];
    }
}
