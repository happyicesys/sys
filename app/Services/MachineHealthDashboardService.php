<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
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
        $channelLimit = max(1, $filters['channel_limit']);
        $targetHours = max(1, $filters['stockout_target_hours']);
        $now = Carbon::now();

        $eventsQuery = VendChannelStockEvent::query()
            ->select([
                'id',
                'vend_channel_id',
                'vend_id',
                'product_id',
                'event_type',
                'occurred_at',
            ])
            ->where('occurred_at', '>=', $lookbackStart)
            ->whereHas('vend', function (EloquentBuilder $query) use ($filters) {
                $this->applyVendFilters($query, $filters);
                $query->where('is_testing', false);
            })
            ->orderBy('vend_channel_id')
            ->orderBy('occurred_at');

        if ($filters['channel_sku']) {
            $eventsQuery->whereHas('vendChannel', function (EloquentBuilder $query) use ($filters) {
                $query->where('sku_code', $filters['channel_sku']);
            });
        }

        $pendingSoldOut = [];
        $topDurations = [];
        $topOpenDurations = [];
        $trendBuckets = [];
        $summaryStats = [
            'closed_count' => 0,
            'within_target' => 0,
            'sum_hours' => 0.0,
            'max_hours' => 0.0,
        ];

        foreach ($eventsQuery->cursor() as $event) {
            $channelId = (int) $event->vend_channel_id;

            if ($event->event_type === VendChannelStockEvent::TYPE_SOLD_OUT) {
                $pendingSoldOut[$channelId] = $event;
                continue;
            }

            if ($event->event_type !== VendChannelStockEvent::TYPE_RESTOCKED || !isset($pendingSoldOut[$channelId])) {
                continue;
            }

            $soldOutEvent = $pendingSoldOut[$channelId];
            $durationMinutes = $soldOutEvent->occurred_at->diffInMinutes($event->occurred_at);
            unset($pendingSoldOut[$channelId]);

            $durationRecord = $this->makeStockoutDurationRecord($soldOutEvent, $event, $durationMinutes, false);
            $this->pushTopDuration($topDurations, $durationRecord, $channelLimit);

            $durationHours = $durationMinutes / 60;
            $summaryStats['closed_count']++;
            $summaryStats['sum_hours'] += $durationHours;
            $summaryStats['max_hours'] = max($summaryStats['max_hours'], $durationHours);
            if ($durationHours <= $targetHours) {
                $summaryStats['within_target']++;
            }

            $stockoutAt = $durationRecord['stockout_at'];
            if ($stockoutAt instanceof Carbon) {
                $weekStart = $stockoutAt->copy()->startOfWeek()->toDateString();
                if (!isset($trendBuckets[$weekStart])) {
                    $trendBuckets[$weekStart] = ['hours' => 0.0, 'count' => 0];
                }

                $trendBuckets[$weekStart]['hours'] += $durationHours;
                $trendBuckets[$weekStart]['count']++;
            }
        }

        foreach ($pendingSoldOut as $soldOutEvent) {
            $durationMinutes = $soldOutEvent->occurred_at->diffInMinutes($now);
            $durationRecord = $this->makeStockoutDurationRecord($soldOutEvent, null, $durationMinutes, true);
            $this->pushTopDuration($topDurations, $durationRecord, $channelLimit);
            $this->pushTopDuration($topOpenDurations, $durationRecord, $channelLimit);
        }

        if ($summaryStats['closed_count'] === 0 && empty($topDurations)) {
            return [
                'summary' => $this->defaultStockoutSummary($targetHours),
                'top_channels' => [],
                'open_channels' => [],
                'trend' => [],
                'metadata' => [
                    'lookback_days' => $filters['stockout_lookback_days'],
                ],
            ];
        }

        $metadata = $this->loadStockoutMetadata($topDurations, $topOpenDurations);

        return [
            'summary' => $summaryStats['closed_count'] > 0
                ? $this->buildStockoutSummaryFromStats($summaryStats, $targetHours)
                : $this->defaultStockoutSummary($targetHours),
            'top_channels' => $this->decorateStockoutDurations($topDurations, $metadata),
            'open_channels' => $this->decorateStockoutDurations($topOpenDurations, $metadata),
            'trend' => $this->buildStockoutTrendFromBuckets($trendBuckets),
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

    private function buildStockoutSummaryFromStats(array $stats, int $targetHours): array
    {
        $closedCount = $stats['closed_count'] ?? 0;
        if ($closedCount === 0) {
            return $this->defaultStockoutSummary($targetHours);
        }

        $avgHours = $stats['sum_hours'] / $closedCount;
        $maxHours = $stats['max_hours'];
        $withinTarget = $stats['within_target'] ?? 0;

        return [
            'average_duration_hours' => round($avgHours, 2),
            'average_duration_days' => round($avgHours / 24, 2),
            'longest_duration_hours' => round($maxHours, 2),
            'longest_duration_days' => round($maxHours / 24, 2),
            'recovery_rate' => round(($withinTarget / $closedCount) * 100, 2),
            'closed_events_count' => $closedCount,
            'target_hours' => $targetHours,
            'target_days' => round($targetHours / 24, 2),
        ];
    }

    private function buildStockoutTrendFromBuckets(array $buckets): array
    {
        if (empty($buckets)) {
            return [];
        }

        ksort($buckets);

        return collect($buckets)
            ->map(function (array $bucket, string $weekStart) {
                $average = $bucket['count'] > 0 ? $bucket['hours'] / $bucket['count'] : 0;

                return [
                    'week_start' => $weekStart,
                    'average_hours' => round($average, 2),
                    'average_days' => round($average / 24, 2),
                    'sample_size' => $bucket['count'],
                ];
            })
            ->values()
            ->all();
    }

    private function makeStockoutDurationRecord(
        VendChannelStockEvent $soldOut,
        ?VendChannelStockEvent $restocked,
        int $durationMinutes,
        bool $isOpen
    ): array {
        return [
            'vend_id' => $soldOut->vend_id ? (int) $soldOut->vend_id : null,
            'vend_channel_id' => $soldOut->vend_channel_id ? (int) $soldOut->vend_channel_id : null,
            'product_id' => $soldOut->product_id ? (int) $soldOut->product_id : null,
            'stockout_at' => $soldOut->occurred_at ? $soldOut->occurred_at->copy() : null,
            'restocked_at' => $restocked?->occurred_at ? $restocked->occurred_at->copy() : null,
            'duration_minutes' => $durationMinutes,
            'is_open' => $isOpen,
        ];
    }

    private function pushTopDuration(array &$list, array $duration, int $limit): void
    {
        if ($limit <= 0) {
            return;
        }

        $list[] = $duration;
        usort($list, fn ($a, $b) => $b['duration_minutes'] <=> $a['duration_minutes']);
        if (count($list) > $limit) {
            $list = array_slice($list, 0, $limit);
        }
    }

    private function loadStockoutMetadata(array ...$durationLists): array
    {
        $vendIds = [];
        $channelIds = [];
        $productIds = [];

        foreach ($durationLists as $durations) {
            foreach ($durations as $duration) {
                if (!empty($duration['vend_id'])) {
                    $vendIds[$duration['vend_id']] = true;
                }

                if (!empty($duration['vend_channel_id'])) {
                    $channelIds[$duration['vend_channel_id']] = true;
                }

                if (!empty($duration['product_id'])) {
                    $productIds[$duration['product_id']] = true;
                }
            }
        }

        $vendCollection = empty($vendIds)
            ? collect()
            : Vend::query()
                ->select('id', 'code', 'name', 'customer_id', 'operator_id', 'vend_prefix_id')
                ->with([
                    'customer:id,name',
                    'operator:id,name',
                    'vendPrefix:id,name',
                ])
                ->whereIn('id', array_keys($vendIds))
                ->get()
                ->keyBy('id');

        $channelCollection = empty($channelIds)
            ? collect()
            : VendChannel::query()
                ->select('id', 'vend_id', 'code', 'product_id')
                ->with(['product:id,name'])
                ->whereIn('id', array_keys($channelIds))
                ->get()
                ->keyBy('id');

        $productCollection = empty($productIds)
            ? collect()
            : Product::query()
                ->select('id', 'name')
                ->whereIn('id', array_keys($productIds))
                ->get()
                ->keyBy('id');

        return [
            'vends' => $vendCollection,
            'channels' => $channelCollection,
            'products' => $productCollection,
        ];
    }

    private function decorateStockoutDurations(array $durations, array $metadata): array
    {
        if (empty($durations)) {
            return [];
        }

        $vends = $metadata['vends'] ?? collect();
        $channels = $metadata['channels'] ?? collect();
        $products = $metadata['products'] ?? collect();

        return collect($durations)
            ->map(function (array $duration) use ($vends, $channels, $products) {
                $vend = $vends->get($duration['vend_id']);
                $channel = $channels->get($duration['vend_channel_id']);
                $product = $duration['product_id'] ? $products->get($duration['product_id']) : null;

                if (!$product && $channel) {
                    $product = $channel->relationLoaded('product') ? $channel->product : null;
                }

                $durationHours = round($duration['duration_minutes'] / 60, 2);

                return [
                    'vend_id' => $duration['vend_id'],
                    'vend_code' => $vend?->code,
                    'vend_name' => $vend?->name,
                    'customer_name' => $vend?->customer?->name,
                    'operator_name' => $vend?->operator?->name,
                    'vend_prefix_name' => $vend?->vendPrefix?->name,
                    'channel_code' => $channel?->code,
                    'product_name' => $product?->name,
                    'duration_hours' => $durationHours,
                    'duration_days' => round($durationHours / 24, 2),
                    'stockout_at' => optional($duration['stockout_at'])->toIso8601String(),
                    'restocked_at' => optional($duration['restocked_at'])->toIso8601String(),
                    'is_open' => $duration['is_open'],
                ];
            })
            ->all();
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

        $recentWindowStart = $now->copy()->subHours(12);

        $recentTempsSubquery = DB::table('vend_temps as vt')
            ->select([
                'vt.vend_id',
                DB::raw('MIN(vt.value) as min_value'),
                DB::raw('MAX(vt.created_at) as last_recorded_at'),
                DB::raw('COUNT(*) as reading_count'),
            ])
            ->where('vt.type', $sensorType)
            ->where('vt.value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->where('vt.created_at', '>=', $recentWindowStart)
            ->whereExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('vends')
                    ->whereColumn('vends.id', 'vt.vend_id')
                    ->where('vends.is_temp_active', true)
                    ->where('vends.is_testing', false);

                $this->applyVendFilters($sub, $filters, 'vends');
            })
            ->groupBy('vt.vend_id');

        $noReachQuery = DB::query()
            ->fromSub($recentTempsSubquery, 'recent_temps')
            ->join('vends', 'recent_temps.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('operators', 'vends.operator_id', '=', 'operators.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
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
                DB::raw('recent_temps.min_value as min_value'),
                DB::raw('recent_temps.last_recorded_at as last_recorded_at'),
                DB::raw('recent_temps.reading_count as reading_count'),
            ])
            ->where('recent_temps.min_value', '>', $minThresholdScaled)
            ->orderByDesc(DB::raw('recent_temps.min_value'))
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
        $nowSql = $now->toDateTimeString();
        $limit = max($filters['machine_limit'] * 2, $filters['machine_limit']);
        $fallbackDate = '1970-01-01 00:00:00';
        $greatestParts = implode(', ', [
            "COALESCE(mqtt_last_updated_at, '{$fallbackDate}')",
            "COALESCE(last_updated_at, '{$fallbackDate}')",
            "COALESCE(last_vend_transaction_at, '{$fallbackDate}')",
            "COALESCE(offline_restart_count_datetime, '{$fallbackDate}')",
        ]);
        $lastContactExpr = "CASE WHEN mqtt_last_updated_at IS NULL AND last_updated_at IS NULL AND last_vend_transaction_at IS NULL AND offline_restart_count_datetime IS NULL THEN NULL ELSE GREATEST({$greatestParts}) END";
        $hoursOfflineExpr = "ROUND(TIMESTAMPDIFF(MINUTE, {$lastContactExpr}, '{$nowSql}') / 60, 2)";

        $vends = $this->baseVendQuery($filters)
            ->where('is_online', false)
            ->select([
                'id',
                'code',
                'name',
                'operator_id',
                'vend_prefix_id',
                'customer_id',
                DB::raw("{$lastContactExpr} as last_contact_at"),
                DB::raw("{$hoursOfflineExpr} as hours_offline"),
            ])
            ->havingRaw('last_contact_at IS NOT NULL')
            ->having('hours_offline', '>=', $primaryThreshold)
            ->orderBy('hours_offline')
            ->limit($limit)
            ->get();

        $primaryList = $vends
            ->map(function (Vend $vend) {
                $lastContact = $vend->last_contact_at ? Carbon::parse($vend->last_contact_at) : null;

                return array_merge(
                    $this->baseVendInfo($vend),
                    [
                        'hours_offline' => (float) $vend->hours_offline,
                        'last_contact_at' => $lastContact?->toIso8601String(),
                    ]
                );
            })
            ->values();

        $secondaryList = $primaryList
            ->filter(fn ($entry) => $entry['hours_offline'] >= $secondaryThreshold)
            ->values();

        return [
            'primary_threshold_hours' => $primaryThreshold,
            'secondary_threshold_hours' => $secondaryThreshold,
            'primary' => $primaryList->take($filters['machine_limit'])->all(),
            'secondary' => $secondaryList->take($filters['machine_limit'])->all(),
        ];
    }

    private function getNoTransactionMetrics(array $filters): array
    {
        $thresholds = $filters['no_txn_threshold_hours'];
        $now = Carbon::now();
        $limit = $filters['machine_limit'];

        return [
            'thresholds' => $thresholds,
            'any_sales' => $this->buildNoTxnList('last_vend_transaction_at', $thresholds['any'], $filters, $now, $limit)->all(),
            'cash_sales' => $this->buildNoTxnList('last_cash_vend_transaction_at', $thresholds['cash'], $filters, $now, $limit)->all(),
            'card_sales' => $this->buildNoTxnList('last_card_vend_transaction_at', $thresholds['card'], $filters, $now, $limit)->all(),
            'qr_sales' => $this->buildNoTxnList('last_cashless_vend_transaction_at', $thresholds['cashless'], $filters, $now, $limit)->all(),
        ];
    }

    private function buildNoTxnList(string $column, int $threshold, array $filters, Carbon $now, int $limit): Collection
    {
        $nowSql = $now->toDateTimeString();
        $hoursExpr = "ROUND(TIMESTAMPDIFF(MINUTE, {$column}, '{$nowSql}') / 60, 2)";

        return $this->baseVendQuery($filters)
            ->select([
                'id',
                'code',
                'name',
                'operator_id',
                'vend_prefix_id',
                'customer_id',
                DB::raw("{$column} as last_transaction_at"),
                DB::raw("{$hoursExpr} as hours_since"),
            ])
            ->whereNotNull($column)
            ->having('hours_since', '>=', $threshold)
            ->orderByDesc('hours_since')
            ->limit($limit)
            ->get()
            ->map(function (Vend $vend) use ($threshold) {
                $lastTransaction = $vend->last_transaction_at ? Carbon::parse($vend->last_transaction_at) : null;

                return array_merge(
                    $this->baseVendInfo($vend),
                    [
                        'hours_since' => (float) $vend->hours_since,
                        'last_transaction_at' => $lastTransaction?->toIso8601String(),
                        'threshold_hours' => $threshold,
                    ]
                );
            });
    }

    private function baseVendQuery(array $filters): EloquentBuilder
    {
        $query = Vend::query()
            ->with([
                'customer:id,name,code',
                'operator:id,name,code',
                'vendPrefix:id,name',
            ])
            ->where('is_testing', false);

        $this->applyVendFilters($query, $filters);

        return $query;
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
