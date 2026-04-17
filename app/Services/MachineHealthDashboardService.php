<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendChannelStockEvent;
use App\Models\VendSmartAlert;
use App\Models\VendTemp;
use App\Models\VendTempMetric;
use App\Models\VendTransaction;
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
            'error_definitions' => VendChannelError::pluck('desc', 'code')->all(),
        ];
    }

    private function hydrateFilters(Request $request): array
    {
        return [
            'machine_limit' => (int) $request->input('machine_limit', 15),
            'channel_limit' => (int) $request->input('channel_limit', 10),
            'error_window_days' => $this->clamp((int) $request->input('error_window_days', 7), 1, 90),
            'temperature_window_days' => $this->clamp((int) $request->input('temperature_window_days', 7), 3, 90),
            'temperature_delta_threshold' => (float) $request->input('temperature_delta_threshold', 3),
            'temperature_min_threshold' => (float) $request->input('temperature_min_threshold', -18),
            'temperature_sensor_type' => (int) $request->input('temperature_sensor_type', VendTemp::TYPE_CHAMBER),
            'temperature_long_window_days' => $this->clamp((int) $request->input('temperature_long_window_days', 30), 7, 120),
            'no_txn_threshold_hours' => [
                'any' => (int) $request->input('no_txn_threshold_hours.any', 48),
                'cash' => (int) $request->input('no_txn_threshold_hours.cash', 48),
                'card' => (int) $request->input('no_txn_threshold_hours.card', 48),
                'digitalscreen' => (int) $request->input('no_txn_threshold_hours.digitalscreen', 72),
                'qr' => (int) $request->input('no_txn_threshold_hours.qr', 72),
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
            'show_all_errors' => $request->boolean('show_all_errors', false),
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

        $eventsQuery->whereHas('vendChannel', function (EloquentBuilder $query) use ($filters) {
            $query->where('is_active', true);
            if ($filters['channel_sku']) {
                $query->where('sku_code', $filters['channel_sku']);
            }
        });

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

            $durationRecord = $this->makeStockoutDurationRecord($soldOutEvent, $event, (int) $durationMinutes, false);
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
            $durationRecord = $this->makeStockoutDurationRecord($soldOutEvent, null, (int) $durationMinutes, true);
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
        usort($list, fn($a, $b) => $b['duration_minutes'] <=> $a['duration_minutes']);
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
        // Ensure we include the entire start day (00:00:00).
        // If windowDays = 1, this goes back to T-1 (Yesterday) 00:00:00, effectively covering Yesterday + Today(partial).
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
                ->join('customers', 'vends.customer_id', '=', 'customers.id')
                ->leftJoin('operators', 'vends.operator_id', '=', 'operators.id')
                ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
                ->whereNotNull('vend_channel_error_logs.vend_transaction_id')
                ->whereBetween('vend_channel_error_logs.created_at', [$periodStart, $periodEnd])
                ->whereIn('vend_channel_error_logs.vend_channel_error_id', $errorIds)
                ->when(!$filters['show_all_errors'], function ($q) {
                    $q->where('vend_channel_error_logs.is_error_cleared', false);
                })
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
                DB::raw('COUNT(DISTINCT vend_channel_error_logs.vend_transaction_id) as total_events'),
                DB::raw('MAX(vend_channel_error_logs.created_at) as last_event_at'),
            ];

            foreach ($definition['codes'] as $code) {
                $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN vend_channel_errors.code = {$code} THEN vend_channel_error_logs.vend_transaction_id END) as code_{$code}_count");
            }

            $rows = $query
                ->select($selects)
                ->groupBy('vends.id', 'vends.code', 'vends.name', 'customers.name', 'operators.name', 'vend_prefixes.name')
                ->orderByDesc('total_events')
                ->limit($limit)
                ->get();

            $vendIds = $rows->pluck('vend_id')->all();
            $detailedEvents = [];

            if (!empty($vendIds)) {
                $detailedEvents = VendChannelErrorLog::query()
                    ->join('vend_channels', 'vend_channel_error_logs.vend_channel_id', '=', 'vend_channels.id')
                    ->join('vend_channel_errors', 'vend_channel_error_logs.vend_channel_error_id', '=', 'vend_channel_errors.id')
                    ->whereIn('vend_channel_error_logs.vend_channel_id', function ($query) use ($vendIds) {
                        $query->select('id')->from('vend_channels')->whereIn('vend_id', $vendIds);
                    })
                    ->whereNotNull('vend_channel_error_logs.vend_transaction_id')
                    ->whereBetween('vend_channel_error_logs.created_at', [$periodStart, $periodEnd])
                    ->whereIn('vend_channel_error_logs.vend_channel_error_id', $errorIds)
                    ->when(!$filters['show_all_errors'], function ($q) {
                        $q->where('vend_channel_error_logs.is_error_cleared', false);
                    })
                    ->select([
                        'vend_channels.vend_id',
                        'vend_channels.code as channel_code',
                        'vend_channel_errors.code as error_code',
                        'vend_channel_error_logs.created_at',
                        'vend_channel_error_logs.is_error_cleared',
                    ])
                    ->orderByDesc('vend_channel_error_logs.created_at')
                    ->get()
                    ->groupBy('vend_id');
            }

            $results[$key] = [
                'label' => $definition['label'],
                'window_days' => $windowDays,
                'limit' => $limit,
                'codes' => $definition['codes'],
                'rows' => $rows->map(function ($row) use ($definition, $detailedEvents) {
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
                        'events' => isset($detailedEvents[$row->vend_id])
                            ? $detailedEvents[$row->vend_id]->map(function ($event) {
                                return [
                                    'channel_code' => $event->channel_code,
                                    'error_code' => $event->error_code,
                                    'created_at' => $event->created_at->toIso8601String(),
                                    'is_error_cleared' => (bool) $event->is_error_cleared,
                                ];
                            })->all()
                            : [],
                    ];
                })->all(),
            ];
        }

        return $results;
    }

    private function getTemperatureMetrics(array $filters): array
    {
        $minThreshold = $filters['temperature_min_threshold'];
        $minThresholdRaw = (int) ($minThreshold * 10);
        $delta = $filters['temperature_delta_threshold'];
        $scale = 10;
        $longWindow = $filters['temperature_long_window_days'];
        $sensorType = $filters['temperature_sensor_type'];

        // Reconstruct Worst Minima Logic
        // $worstMinimaRows = $this->baseVendModelQuery($filters)
        //     ->join('vend_temp_metrics', function ($join) use ($longWindow, $sensorType) {
        //         $join->on('vends.id', '=', 'vend_temp_metrics.vend_id')
        //             ->where('vend_temp_metrics.period_type', VendTempMetric::PERIOD_DAILY)
        //             ->where('vend_temp_metrics.temp_type', $sensorType)
        //             ->where('vend_temp_metrics.period_start', '>=', Carbon::today()->subDays($longWindow));
        //     })
        //     ->select([
        //         'vends.id as vend_id',
        //         'vends.code as vend_code',
        //         'vends.name as vend_name',
        //         'vends.customer_id',
        //         'vends.operator_id',
        //         'vends.vend_prefix_id',
        //         DB::raw('MAX(vend_temp_metrics.min_temp_value) as worst_min_val'),
        //         DB::raw('MIN(vend_temp_metrics.min_temp_value) as best_min_val'),
        //         DB::raw('MAX(vend_temp_metrics.min_temp_recorded_at) as last_recorded_at'),
        //     ])
        //     ->groupBy('vends.id', 'vends.code', 'vends.name', 'vends.customer_id', 'vends.operator_id', 'vends.vend_prefix_id')
        //     ->having('worst_min_val', '>', $minThresholdRaw)
        //     ->orderByDesc('worst_min_val')
        //     ->limit($filters['machine_limit'])
        //     ->with(['customer:id,name', 'operator:id,name', 'vendPrefix:id,name'])
        //     ->get()
        //     ->map(function ($row) use ($scale) {
        //         return [
        //             'vend_id' => $row->vend_id,
        //             'vend_code' => $row->vend_code,
        //             'vend_name' => $row->vend_name,
        //             'customer_name' => $row->customer->name ?? null,
        //             'operator_name' => $row->operator->name ?? null,
        //             'vend_prefix_name' => $row->vendPrefix->name ?? null,
        //             'worst_min_temp' => $row->worst_min_val / $scale,
        //             'best_min_temp' => ($row->best_min_val ?? 0) / $scale,
        //             'last_recorded_at' => $row->last_recorded_at,
        //         ];
        //     })
        //     ->all();

        // Not Reaching Threshold Logic
        // $recentWindowStart = Carbon::now()->subHours(12);

        // $notReaching = $this->baseVendQuery($filters)
        //     ->join('vend_temps as recent_temps', function ($join) use ($recentWindowStart, $sensorType) {
        //         $join->on('vends.id', '=', 'recent_temps.vend_id')
        //             ->where('recent_temps.created_at', '>=', $recentWindowStart)
        //             ->where('recent_temps.type', $sensorType)
        //             ->where('recent_temps.value', '!=', VendTemp::TEMPERATURE_ERROR);
        //     })
        //     ->select([
        //         'vends.id',
        //         'vends.code',
        //         'vends.name',
        //         'vends.operator_id',
        //         'vends.customer_id',
        //         'vends.vend_prefix_id',
        //         DB::raw('MIN(recent_temps.value) as min_value'),
        //         DB::raw('MAX(recent_temps.created_at) as last_recorded_at'),
        //         DB::raw('COUNT(recent_temps.id) as reading_count'),
        //     ])
        //     ->groupBy('vends.id', 'vends.code', 'vends.name', 'vends.operator_id', 'vends.customer_id', 'vends.vend_prefix_id')
        //     ->having('min_value', '>', $minThresholdRaw)
        //     ->orderByDesc('min_value')
        //     ->limit($filters['machine_limit'])
        //     ->get()
        //     ->map(function ($row) use ($scale) {
        //         return array_merge($this->baseVendInfo($row), [
        //             'min_value' => $row->min_value / $scale,
        //             'last_recorded_at' => $row->last_recorded_at ? Carbon::parse($row->last_recorded_at)->toIso8601String() : null,
        //             'reading_count' => $row->reading_count,
        //         ]);
        //     })
        //     ->all();

        return [
            // 'rising_lowest' => $this->getRisingLowestResults($filters), // Disable backend loading
            'rising_lowest' => [],
            'rising_lowest_t1_smart' => $this->getSmartAlerts($filters, [VendSmartAlert::TYPE_RISING_T1]),
            'rising_lowest_t2_smart' => $this->getSmartAlerts($filters, [VendSmartAlert::TYPE_RISING_T2]),
            't2_frozen_smart' => $this->getSmartAlerts($filters, [VendSmartAlert::TYPE_T2_FROZEN]),
            'operation_errors_smart' => $this->getSmartAlerts($filters, [
                VendSmartAlert::TYPE_COMP_FAN_OFF,
                VendSmartAlert::TYPE_TEMPS_ABOVE_0,
                VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8,
                VendSmartAlert::TYPE_NOT_REACH_MINUS_18
            ]),
            'preventive_maintenance_smart' => $this->getSmartAlerts($filters, [
                VendSmartAlert::TYPE_LOWEST_24H_ABOVE,
                VendSmartAlert::TYPE_LOWEST_72H_ABOVE
            ]),
            'worst_minima' => [
                'window_days' => $longWindow,
                'rows' => [], // $worstMinimaRows,
            ],
            'not_reaching_threshold' => [
                'threshold' => $minThreshold,
                'hours' => 12,
                'rows' => [], // $notReaching,
            ],
        ];
    }

    private function getRisingLowestResults(array $filters): array
    {
        $window = $filters['temperature_window_days'];
        $delta = $filters['temperature_delta_threshold'];
        $limit = $filters['machine_limit'];
        $scale = 10;
        $sensorType = $filters['temperature_sensor_type'];

        $startA = Carbon::today()->subDays($window);
        $endA = Carbon::today();
        $startB = Carbon::today()->subDays($window * 2);
        $endB = Carbon::today()->subDays($window);

        // Subquery A
        $metricsA = DB::table('vend_temp_metrics')
            ->select('vend_id', DB::raw('MIN(min_temp_value) as min_a'))
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereBetween('period_start', [$startA, $endA])
            ->where('temp_type', $sensorType)
            ->groupBy('vend_id');

        // Subquery B
        $metricsB = DB::table('vend_temp_metrics')
            ->select('vend_id', DB::raw('MIN(min_temp_value) as min_b'))
            ->where('period_type', VendTempMetric::PERIOD_DAILY)
            ->whereBetween('period_start', [$startB, $endB])
            ->where('temp_type', $sensorType)
            ->groupBy('vend_id');

        $rows = $this->baseVendQuery($filters)
            ->joinSub($metricsA, 'metrics_a', 'vends.id', '=', 'metrics_a.vend_id')
            ->joinSub($metricsB, 'metrics_b', 'vends.id', '=', 'metrics_b.vend_id')
            ->select('vends.*', 'metrics_a.min_a', 'metrics_b.min_b')
            ->whereRaw('(metrics_a.min_a - metrics_b.min_b) >= ?', [$delta * $scale])
            ->orderByDesc(DB::raw('metrics_a.min_a - metrics_b.min_b'))
            ->limit($limit)
            ->get();

        return [
            'window_days' => $window,
            'rows' => $rows->map(function ($row) use ($scale, $startA, $endA, $startB, $endB) {
                $minA = $row->min_a / $scale;
                $minB = $row->min_b / $scale;
                return array_merge($this->baseVendInfo($row), [
                    'first_day' => 'Prev Period',
                    'latest_day' => 'Curr Period',
                    'first_min_temp' => $minB,
                    'latest_min_temp' => $minA,
                    'delta' => $minA - $minB,
                ]);
            })->all()
        ];
    }

    private function getConnectivityMetrics(array $filters): array
    {
        $now = Carbon::now();
        $nowSql = $now->toDateTimeString();
        // Increase limit to accommodate all potential offline machines across buckets
        $limit = 500;
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
                'acb_vmc_pa_json',
                DB::raw("{$lastContactExpr} as last_contact_at"),
                DB::raw("{$hoursOfflineExpr} as hours_offline"),
            ])
            ->havingRaw('last_contact_at IS NOT NULL')
            ->having('hours_offline', '<', 60)
            ->orderBy('hours_offline')
            ->limit($limit)
            ->get();

        $buckets = [
            '< 1hr' => [],
            '< 2hr' => [],
            '< 4hr' => [],
            '< 8hr' => [],
            '< 12hr' => [],
            '> 12hr' => [],
        ];

        foreach ($vends as $vend) {
            $hours = (float) $vend->hours_offline;
            $row = array_merge(
                $this->baseVendInfo($vend),
                [
                    'hours_offline' => $hours,
                    'last_contact_at' => $vend->last_contact_at ? Carbon::parse($vend->last_contact_at)->toIso8601String() : null,
                    'acb_vmc_pa_json' => $vend->acb_vmc_pa_json,
                ]
            );

            if ($hours >= 0.25 && $hours < 1) {
                $buckets['< 1hr'][] = $row;
            } elseif ($hours < 0.25) {
                // Skip machines offline for less than 15 minutes
                continue;
            } elseif ($hours < 2) {
                $buckets['< 2hr'][] = $row;
            } elseif ($hours < 4) {
                $buckets['< 4hr'][] = $row;
            } elseif ($hours < 8) {
                $buckets['< 8hr'][] = $row;
            } elseif ($hours < 12) {
                $buckets['< 12hr'][] = $row;
            } else {
                $buckets['> 12hr'][] = $row;
            }
        }

        return [
            'buckets' => array_map(function ($label, $rows) {
                return ['label' => $label, 'rows' => $rows];
            }, array_keys($buckets), array_values($buckets)),
        ];
    }

    private function getNoTransactionMetrics(array $filters): array
    {
        $thresholds = $filters['no_txn_threshold_hours'];
        $now = Carbon::now();
        $limit = $filters['machine_limit'];

        // Fetch raw Vend model collections (no eager-loading yet)
        $anySalesModels = $this->buildNoTxnList('last_vend_transaction_at', $thresholds['any'], $filters, $now, $limit);
        $cashSalesModels = $this->buildNoTxnList('last_cash_vend_transaction_at', $thresholds['cash'], $filters, $now, $limit, 'cash');
        $cardSalesModels = $this->buildNoTxnList('last_card_vend_transaction_at', $thresholds['card'], $filters, $now, $limit, 'card');
        $qrSalesModels = $this->buildNoTxnList('last_txn_src_at', $thresholds['qr'], $filters, $now, $limit, 'qr');
        $digitalScreenSalesModels = $this->buildNoTxnList('last_txn_src_at', $thresholds['digitalscreen'], $filters, $now, $limit, 'digitalscreen');

        // Load relations once across every single object instance in all lists to prevent lazy-loading N+1.
        // We use array_merge to ensure we don't overwrite duplicate models per-vend-id like merge() does.
        $allModelsArray = array_merge(
            $anySalesModels->all(),
            $cashSalesModels->all(),
            $cardSalesModels->all(),
            $qrSalesModels->all(),
            $digitalScreenSalesModels->all()
        );

        $allModels = new \Illuminate\Database\Eloquent\Collection($allModelsArray);
        $allModels->loadMissing(['customer:id,name,code', 'operator:id,name,code', 'vendPrefix:id,name']);

        $allVendIds = $allModels->pluck('id')->unique()->values()->all();

        $l30dSales = [];
        if (!empty($allVendIds)) {
            $l30dStart = Carbon::now()->subDays(30);
            $l30dSales = VendTransaction::query()
                ->whereIn('vend_id', $allVendIds)
                ->where('transaction_datetime', '>=', $l30dStart)
                ->groupBy('vend_id')
                ->selectRaw('vend_id, SUM(amount) as total_sales')
                ->pluck('total_sales', 'vend_id')
                ->all();
        }

        $transformList = function (Collection $models, int $threshold) use ($l30dSales): array {
            return $models->map(function (Vend $vend) use ($threshold, $l30dSales) {
                $lastTransaction = $vend->last_transaction_at ? Carbon::parse($vend->last_transaction_at) : null;

                return array_merge(
                    $this->baseVendInfo($vend),
                    [
                        'hours_since' => (float) $vend->hours_since,
                        'last_transaction_at' => $lastTransaction?->toIso8601String(),
                        'threshold_hours' => $threshold,
                        'acb_vmc_pa_json' => $vend->acb_vmc_pa_json,
                        'parameter_json' => $vend->parameter_json,
                        'l30d_sales' => (int) ($l30dSales[$vend->id] ?? 0),
                    ]
                );
            })->values()->all();
        };

        return [
            'thresholds' => $thresholds,
            'any_sales' => $transformList($anySalesModels, $thresholds['any']),
            'cash_sales' => $transformList($cashSalesModels, $thresholds['cash']),
            'card_sales' => $transformList($cardSalesModels, $thresholds['card']),
            'qr_sales' => $transformList($qrSalesModels, $thresholds['qr']),
            'digitalscreen_sales' => $transformList($digitalScreenSalesModels, $thresholds['digitalscreen']),
        ];
    }

    /**
     * Fetches raw Vend models for a no-transaction list WITHOUT eager-loading relations.
     * Relations are loaded in bulk by the caller (getNoTransactionMetrics) to avoid
     * duplicate queries across the 5 separate list types.
     */
    private function buildNoTxnList(string $column, int $threshold, array $filters, Carbon $now, int $limit, ?string $type = null): Collection
    {
        $nowSql = $now->toDateTimeString();
        $hoursExpr = "ROUND(TIMESTAMPDIFF(MINUTE, {$column}, '{$nowSql}') / 60, 2)";

        $query = $this->baseVendQuery($filters)
            ->without(['customer', 'operator', 'vendPrefix']) // Relations loaded in bulk by caller
            ->select([
                'id',
                'code',
                'name',
                'operator_id',
                'vend_prefix_id',
                'customer_id',
                'acb_vmc_pa_json',
                'parameter_json',
                DB::raw("{$column} as last_transaction_at"),
                DB::raw("{$hoursExpr} as hours_since"),
            ])
            ->whereNotNull('customer_id')
            ->has('customer')
            ->whereNotNull($column)
            ->having('hours_since', '>=', $threshold)
            ->orderByDesc('hours_since')
            ->limit($limit);

        if ($type === 'cash') {
            $query->where('parameter_json->BILLStat', 3);
        } elseif ($type === 'card') {
            $query->where('parameter_json->CSHLStat', 3);
        } elseif ($type === 'digitalscreen') {
            $query->where('is_txn_src', true);
        } elseif ($type === 'qr') {
            $query->where('is_txn_src', true)
                ->where('acb_vmc_pa_json->QRCode', 1);
        }

        // Return raw Vend models; transformation happens in getNoTransactionMetrics
        return $query->get();
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

        if (!empty($filters['channel_sku']) && $query instanceof EloquentBuilder && $query->getModel() instanceof Vend) {
            $sku = $filters['channel_sku'];
            $query->whereHas('vendChannels', function ($q) use ($sku) {
                $q->where('sku_code', $sku)
                    ->orWhere('sku_code', 'LIKE', "{$sku}%");
            });
        }

        if ($query instanceof EloquentBuilder && $query->getModel() instanceof Vend) {
            $query->has('customer');
        } else {
            $query->whereNotNull("{$table}.customer_id");
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

    private function baseVendModelQuery(array $filters)
    {
        $query = Vend::query()->where('is_testing', false);
        $this->applyVendFilters($query, $filters);
        return $query;
    }

    private function getSmartAlerts(array $filters, array $types): array
    {
        $limit = $filters['machine_limit'];
        $alertsQuery = VendSmartAlert::query()
            ->with([
                'vend:id,code,name,operator_id,vend_prefix_id,customer_id,temp,parameter_json',
                'vend.customer:id,name',
                'vend.operator:id,name',
                'vend.vendPrefix:id,name',
            ])
            ->whereIn('alert_type', $types)
            ->where('is_active', true)
            ->whereHas('vend', function ($query) use ($filters) {
                $this->applyVendFilters($query, $filters);
                $query->where('is_testing', false)
                    ->where('is_online', true);
            })
            ->orderByDesc('severity')
            ->orderByDesc('updated_at')
            ->limit($limit);

        $alerts = $alertsQuery->get()->unique('vend_id')->values();

        return [
            'rows' => $alerts->map(function ($alert) {
                $vend = $alert->vend;
                $meta = $alert->meta_data;
                $scale = 10;

                $duration = $meta['duration_label'] ?? $meta['duration'] ?? null;
                $now = now();

                // Dynamic calculation if we have a starting timestamp
                $startTime = $meta['started_at'] ?? $meta['min_timestamp'] ?? $meta['triggered_at'] ?? null;
                if ($startTime) {
                    try {
                        $start = Carbon::parse($startTime);
                        $duration = $start->diffInMinutes($now) / 60;
                    } catch (\Exception $e) {
                        // Fallback to stored duration if parsing fails
                    }
                } elseif (is_numeric($duration)) {
                    // Convert Minutes to Hours for types that store Minutes
                    if (
                        in_array($alert->alert_type, [
                            VendSmartAlert::TYPE_COMP_FAN_OFF,
                            VendSmartAlert::TYPE_TEMPS_ABOVE_0,
                            VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8,
                            VendSmartAlert::TYPE_LOWEST_24H_ABOVE,
                            VendSmartAlert::TYPE_LOWEST_72H_ABOVE,
                            VendSmartAlert::TYPE_NOT_REACH_MINUS_18,
                            VendSmartAlert::TYPE_RISING_T1,
                            VendSmartAlert::TYPE_RISING_T2
                        ])
                    ) {
                        $duration = $duration / 60;
                    }
                } elseif ($duration === null) {
                    // Default durations for types that don't satisfy explicit duration
                    switch ($alert->alert_type) {
                        case VendSmartAlert::TYPE_LOWEST_24H_ABOVE:
                        case VendSmartAlert::TYPE_RISING_T1:
                        case VendSmartAlert::TYPE_RISING_T2:
                            $duration = 24;
                            break;
                        case VendSmartAlert::TYPE_LOWEST_72H_ABOVE:
                            $duration = 72;
                            break;
                        case VendSmartAlert::TYPE_NOT_REACH_MINUS_18:
                            $duration = ($alert->severity == 2) ? 12 : 8;
                            break;
                    }
                }

                return [
                    'vend_id' => $vend?->id,
                    'vend_code' => $vend?->code,
                    'vend_name' => $vend?->name,
                    'customer_name' => $vend?->customer?->name,
                    'operator_name' => $vend?->operator?->name,
                    'vend_prefix_name' => $vend?->vendPrefix?->name,
                    'temp' => $vend?->temp,
                    'parameter_json' => $vend?->parameter_json,
                    'first_min_temp' => $meta['prev_min'] ?? null,
                    'first_min_temp_at' => $meta['prev_min_timestamp'] ?? null,
                    'latest_min_temp' => $meta['current_min'] ?? null,
                    'delta' => $meta['delta'] ?? $meta['diff'] ?? null,
                    'meta_max_temp' => $meta['max_temp'] ?? null,
                    'meta_duration' => $duration,
                    'meta_val' => $meta['val'] ?? null,
                    'meta_min_t1' => $meta['min_t1'] ?? $meta['v1'] ?? null,
                    'meta_min_t2' => $meta['min_t2'] ?? $meta['v2'] ?? null,
                    'severity' => $alert->severity,
                    'alert_type' => $alert->alert_type,
                    'updated_at' => $meta['min_timestamp'] ?? $meta['calculated_at'] ?? $alert->updated_at->toIso8601String(),
                ];
            })->all(),
        ];
    }

    private function normalizeIdArray($input): array
    {
        return array_values(array_filter(array_map('intval', $this->normalizeArray($input)), fn($value) => $value > 0));
    }

    private function normalizeStringArray($input): array
    {
        return array_values(array_filter(array_map(static fn($value) => trim((string) $value), $this->normalizeArray($input)), fn($value) => $value !== ''));
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
