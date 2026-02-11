<?php

namespace App\Jobs;

use App\Models\VendSmartAlert;
use App\Models\VendTemp;
use App\Models\VendLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectTempTrends implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    // Prevent duplicate jobs for same vend for 10 minutes
    public $uniqueFor = 600;

    public function uniqueId()
    {
        // Differentiate between lightweight/realtime and heavy/scheduled jobs
        // so they don't block each other unnecessarily, effectively.
        // Or keep them same to prevent race conditions on DB?
        // Safer to keep same ID (vendID) lock.
        return $this->targetVendId ? $this->targetVendId . '-' . ($this->isFullScan ? 'full' : 'light') : 'global';
    }

    public ?int $targetVendId;
    public bool $isFullScan = false;

    /**
     * Create a new job instance.
     * @param int|null $vendId
     * @param bool $isFullScan If true, runs the heavy hourly analysis (Trends, Frozen, etc.)
     */
    public function __construct($vendId = null, $isFullScan = false)
    {
        $this->targetVendId = $vendId;
        $this->isFullScan = $isFullScan;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Global Schedule Run (No ID)
        if (!$this->targetVendId) {
            // Dispatch individual full-scan jobs for all active vends to prevent timeouts.
            \App\Models\Vend::where('is_active', true)
                ->where('is_testing', false)
                ->select('id')
                ->chunk(100, function ($vends) {
                    foreach ($vends as $vend) {
                        self::dispatch($vend->id, true)->onQueue('default');
                    }
                });
            return;
        }

        // 2. Individual Run
        // Always run stateful (Real-time 2.1 Operation Errors)
        $this->analyzeStateful($this->targetVendId);

        // Check Connectivity (1)
        $this->checkConnectivity($this->targetVendId);

        // 2. heavy trends (Hourly)
        if ($this->isFullScan) {
            // 2.2: Rising Trends
            $this->analyzeTrend(VendTemp::TYPE_CHAMBER, VendSmartAlert::TYPE_RISING_T1);
            $this->analyzeTrend(VendTemp::TYPE_EVAPORATOR, VendSmartAlert::TYPE_RISING_T2);

            // 2.1: Frozen T2
            $this->analyzeFrozenT2();

            // 2.1 Operation Errors: REMOVED.
            // We rely on analyzeStateful() which runs in both modes (Realtime & Hourly)
            // and persists state to DB, avoiding redundant heavy historical queries.

            // 2.2: Preventive (High Minima)
            $this->analyzePreventiveMaintenance();
        }
    }

    private function getTargetVends()
    {
        if ($this->targetVendId) {
            return [$this->targetVendId];
        }
        return \App\Models\Vend::where('is_active', true)->where('is_testing', false)->pluck('id');
    }

    private function analyzeFrozenT2(): void
    {
        // Optimization: Use target vend if available
        $vends = $this->targetVendId
            ? \App\Models\Vend::where('id', $this->targetVendId)->whereIn('menu_frame_id', [5, 6, 7, 10])->get()
            : $this->getFrozenT2Candidates();

        $scale = 10;
        $thresholdRaw = 2 * $scale;
        $now = now();
        $calc72h = $now->copy()->subHours(72);

        foreach ($vends as $vend) {
            // 1. Check latest status first (Fail fast)
            $latestT2 = VendTemp::where('vend_id', $vend->id)
                ->where('type', VendTemp::TYPE_EVAPORATOR)
                ->latest()
                ->first();

            // Dismiss if Error Code or Missing
            if (!$latestT2 || $latestT2->value == VendTemp::TEMPERATURE_ERROR) {
                $this->clearSmartAlert($vend->id, VendSmartAlert::TYPE_T2_FROZEN);
                continue;
            }

            // 2. Single Aggregate Query for 24h/48h/72h metrics
            // Fetch Max/Min stats efficiently
            $stats = VendTemp::where('vend_id', $vend->id)
                ->where('type', VendTemp::TYPE_EVAPORATOR)
                ->where('created_at', '>=', $calc72h)
                ->selectRaw('
                    MAX(CASE WHEN created_at >= ? THEN value END) as max_24,
                    MIN(CASE WHEN created_at >= ? THEN value END) as min_24,
                    MAX(CASE WHEN created_at >= ? THEN value END) as max_48,
                    MAX(value) as max_72
                ', [
                    $now->copy()->subHours(24),
                    $now->copy()->subHours(24),
                    $now->copy()->subHours(48)
                ])
                ->first();

            if (!$stats)
                continue;

            $max24 = $stats->max_24;
            $min24 = $stats->min_24;

            // Dismiss if:
            // 1. T2 > 2C (Defrosted/Working) in last 24h
            // 2. T2 <= -23.5C (Reached very cold temp) in last 24h
            $dismissThresholdRaw = -23.5 * $scale;
            if ($max24 === null || $max24 > $thresholdRaw || ($min24 !== null && $min24 <= $dismissThresholdRaw)) {
                $this->clearSmartAlert($vend->id, VendSmartAlert::TYPE_T2_FROZEN);
                continue;
            }

            $max48 = $stats->max_48;
            $max72 = $stats->max_72;

            $durationLabel = '> 24 hr';
            $severity = 1;
            $metaMax = $max24;
            $windowHr = 24;

            if ($max72 !== null && $max72 <= $thresholdRaw) {
                $durationLabel = '> 72 hr';
                $severity = 3;
                $metaMax = $max72;
                $windowHr = 72;
            } elseif ($max48 !== null && $max48 <= $thresholdRaw) {
                $durationLabel = '> 48 hr';
                $severity = 2;
                $metaMax = $max48;
                $windowHr = 48;
            }

            // Get latest timestamp in window
            $latestFrozen = VendTemp::where('vend_id', $vend->id)
                ->where('type', VendTemp::TYPE_EVAPORATOR)
                ->where('created_at', '>=', now()->subHours($windowHr))
                ->latest('created_at')
                ->first();

            $minTimestamp = $latestFrozen ? $latestFrozen->created_at->toIso8601String() : $now->toIso8601String();

            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vend->id, 'alert_type' => VendSmartAlert::TYPE_T2_FROZEN],
                [
                    'severity' => $severity,
                    'is_active' => true,
                    'meta_data' => [
                        'val' => $metaMax / $scale,
                        'max_temp' => $metaMax / $scale,
                        'duration_label' => $durationLabel,
                        'calculated_at' => $now->toIso8601String(),
                        'min_timestamp' => $minTimestamp,
                    ],
                ]
            );
        }
    }

    private function getFrozenT2Candidates()
    {
        return \App\Models\Vend::query()
            ->select('id', 'code', 'vend_prefix_id')
            ->whereIn('menu_frame_id', [5, 6, 7, 10]) // 5,6,7,10 are standard frames
            ->whereHas('vendPrefix', function ($q) {
                $q->where('name', 'NOT LIKE', '%UUD%')
                    ->where('name', 'NOT LIKE', '%UDD%');
            })
            ->where('is_active', true)
            ->where('is_testing', false)
            ->get();
    }

    private function clearSmartAlert($vendId, $type)
    {
        VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $type)->update(['is_active' => false]);
    }

    private function analyzePreventiveMaintenance(): void
    {
        // 2.2 Preventive Logic
        $vends = $this->getTargetVends();
        $scale = 10;

        foreach ($vends as $vendId) {
            $this->checkLowestAbove($vendId, 24, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_24H_ABOVE, $scale);
            $this->checkLowestAbove($vendId, 72, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_72H_ABOVE, $scale);
        }
    }

    private function checkLowestAbove($vendId, $hours, $thresholds, $alertType, $scale)
    {
        // Optimized: Get both min values in one query
        $temps = VendTemp::where('vend_id', $vendId)
            ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
            ->where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('type, MIN(value) as min_value')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $minT1 = $temps->get(VendTemp::TYPE_CHAMBER)?->min_value;
        $minT2 = $temps->get(VendTemp::TYPE_EVAPORATOR)?->min_value;

        if ($minT1 === null || $minT2 === null)
            return;

        $v1 = $minT1 / $scale;
        $v2 = $minT2 / $scale;

        $sev = 0;
        // thresholds = [-21, -20, -19]
        if ($v1 > $thresholds[2] && $v2 > $thresholds[2])
            $sev = 3;
        elseif ($v1 > $thresholds[1] && $v2 > $thresholds[1])
            $sev = 2;
        elseif ($v1 > $thresholds[0] && $v2 > $thresholds[0])
            $sev = 1;

        $duration = 0;
        if ($sev > 0) {
            // Calculate duration: Time since last "Good" reading (<= threshold)
            // Determine the threshold for the current severity
            // severity 3 uses thresholds[2], severity 2 uses thresholds[1], severity 1 uses thresholds[0]
            $activeThreshold = $thresholds[$sev - 1];
            $threshRaw = $activeThreshold * $scale;

            // Find last time EITHER T1 or T2 was <= activeThreshold
            $lastGood = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('value', '<=', $threshRaw)
                ->latest('created_at')
                ->first();

            if ($lastGood) {
                $duration = now()->diffInMinutes($lastGood->created_at);
            } else {
                // If no good reading found, use the window plus some buffer or just the window?
                // Using the window (hours) converted to minutes as a fallback minimum.
                // Or maybe search deeper? For performance, let's limit to looking back a reasonable amount,
                // but here we just fallback to the window if nothing found (which implies at least window duration).
                $duration = $hours * 60;
            }

            // Find valid timestamp for min T1
            $minT1Record = VendTemp::where('vend_id', $vendId)
                ->where('type', VendTemp::TYPE_CHAMBER)
                ->where('created_at', '>=', now()->subHours($hours))
                ->orderBy('value', 'asc')
                ->first();

            $minTimestamp = $minT1Record ? $minT1Record->created_at->toIso8601String() : now()->toIso8601String();

            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => ['min_t1' => $v1, 'min_t2' => $v2, 'duration' => $duration, 'min_timestamp' => $minTimestamp]]
            );
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
        }
    }

    private function analyzeTrend(int $tempType, string $alertType): void
    {
        $now = now();
        $windowCurrentStart = $now->copy()->subHours(24);
        $windowPrevStart = $now->copy()->subHours(48);

        // Pre-fetch all relevant temps for the last 48 hours to minimize queries?
        // Or do it per vend? Given memory constraints, per vend (or chunked vends) is safer.
        // Let's use a query that aggregates per vend to be efficient.

        // 1. Get Min T for [Now-24h, Now]
        $currentQuery = VendTemp::query()
            ->select('vend_id', DB::raw('MIN(value) as min_val'))
            ->where('type', $tempType)
            ->whereBetween('created_at', [$windowCurrentStart, $now])
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR);

        if ($this->targetVendId) {
            $currentQuery->where('vend_id', $this->targetVendId);
        }

        $currentMins = $currentQuery->groupBy('vend_id')->pluck('min_val', 'vend_id');

        // 2. Get Min T for [Now-48h, Now-24h]
        $prevQuery = VendTemp::query()
            ->select('vend_id', DB::raw('MIN(value) as min_val'))
            ->where('type', $tempType)
            ->whereBetween('created_at', [$windowPrevStart, $windowCurrentStart])
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR);

        if ($this->targetVendId) {
            $prevQuery->where('vend_id', $this->targetVendId);
        }

        $prevMins = $prevQuery->groupBy('vend_id')->pluck('min_val', 'vend_id');

        $deviceIds = $currentMins->keys()->merge($prevMins->keys())->unique();

        foreach ($deviceIds as $vendId) {
            $curr = $currentMins->get($vendId);
            $prev = $prevMins->get($vendId);

            // Need both to compare
            if (is_null($curr) || is_null($prev)) {
                // If data missing, resolve existing alert if any
                VendSmartAlert::where('vend_id', $vendId)
                    ->where('alert_type', $alertType)
                    ->update(['is_active' => false]);
                continue;
            }

            // Exclude Sensor Error (3276.7C -> 32767 raw)
            // If either value is the error code, skip and clear alert
            if (abs($curr - 32767) < 1 || abs($prev - 32767) < 1) {
                VendSmartAlert::where('vend_id', $vendId)
                    ->where('alert_type', $alertType)
                    ->update(['is_active' => false]);
                continue;
            }

            // Values are stored as integers (e.g. 150 = 15.0C), scale is 10
            $deltaRaw = $curr - $prev;
            $scale = 10;
            $deltaC = $deltaRaw / $scale;

            if ($deltaC >= 1.0) {
                $severity = 1;
                if ($deltaC >= 3.0)
                    $severity = 3;
                elseif ($deltaC >= 2.0)
                    $severity = 2;

                $existingAlert = VendSmartAlert::where('vend_id', $vendId)
                    ->where('alert_type', $alertType)
                    ->first();

                $startedAt = $now->toIso8601String();
                if ($existingAlert && $existingAlert->is_active && isset($existingAlert->meta_data['started_at'])) {
                    $startedAt = $existingAlert->meta_data['started_at'];
                }

                $elapsedMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($startedAt));
                $duration = (24 * 60) + $elapsedMinutes;

                $minTimestamp = $this->findMinTimestamp($vendId, $tempType, $windowCurrentStart, $now, $curr);

                VendSmartAlert::updateOrCreate(
                    [
                        'vend_id' => $vendId,
                        'alert_type' => $alertType,
                    ],
                    [
                        'severity' => $severity,
                        'is_active' => true,
                        'meta_data' => [
                            'current_min' => $curr / $scale,
                            'prev_min' => $prev / $scale,
                            'delta' => $deltaC,
                            'calculated_at' => $now->toIso8601String(),
                            'started_at' => $startedAt,
                            'duration' => $duration,
                            'min_timestamp' => $minTimestamp,
                            'prev_min_timestamp' => $this->findMinTimestamp($vendId, $tempType, $windowPrevStart, $windowCurrentStart, $prev),
                        ],
                    ]
                );
            } else {
                VendSmartAlert::where('vend_id', $vendId)
                    ->where('alert_type', $alertType)
                    ->update(['is_active' => false]);
            }
        }
    }

    private function analyzeStateful(int $vendId): void
    {
        $vend = \App\Models\Vend::find($vendId);
        if (!$vend || !$vend->is_active || $vend->is_testing) {
            return;
        }

        // Get latest temps (O(1) with index)
        $t1 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)->latest()->first();
        $t2 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)->latest()->first();

        // If no recent data skip.
        if (!$t1 || !$t2)
            return;

        $t1Val = $t1->value / 10;
        $t2Val = $t2->value / 10;
        $now = now();

        $state = $vend->temp_monitoring_state ?? [];
        $newState = $state;

        // --- Logic: T2 < -25C (Frozen?) ---
        if ($t2Val < -25) {
            if (!isset($state['t2_lt_minus_25_start'])) {
                $newState['t2_lt_minus_25_start'] = $now->toIso8601String();
            }
        } else {
            unset($newState['t2_lt_minus_25_start']);
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_T2_BELOW_MINUS_25)->update(['is_active' => false]);
        }

        // --- Logic: T1 & T2 > 0 (Warm) ---
        if ($t1Val > 0) {
            if (!isset($state['t1_gt_0_start']))
                $newState['t1_gt_0_start'] = $now->toIso8601String();
        } else {
            unset($newState['t1_gt_0_start']);
        }
        if ($t2Val > 0) {
            if (!isset($state['t2_gt_0_start']))
                $newState['t2_gt_0_start'] = $now->toIso8601String();
        } else {
            unset($newState['t2_gt_0_start']);
        }

        // --- Logic: T1 & T2 > -8 (Semi-Warm) ---
        if ($t1Val > -8) {
            if (!isset($state['t1_gt_minus_8_start']))
                $newState['t1_gt_minus_8_start'] = $now->toIso8601String();
        } else {
            unset($newState['t1_gt_minus_8_start']);
        }
        if ($t2Val > -8) {
            if (!isset($state['t2_gt_minus_8_start']))
                $newState['t2_gt_minus_8_start'] = $now->toIso8601String();
        } else {
            unset($newState['t2_gt_minus_8_start']);
        }

        // --- Logic: Not Reached -18 (Both > -18) ---
        if ($t1Val > -18) {
            if (!isset($state['t1_gt_minus_18_start']))
                $newState['t1_gt_minus_18_start'] = $now->toIso8601String();
        } else {
            unset($newState['t1_gt_minus_18_start']);
        }
        if ($t2Val > -18) {
            if (!isset($state['t2_gt_minus_18_start']))
                $newState['t2_gt_minus_18_start'] = $now->toIso8601String();
        } else {
            unset($newState['t2_gt_minus_18_start']);
        }

        // Save State
        if ($state !== $newState) {
            $vend->temp_monitoring_state = $newState;
            $vend->save();
        }

        // --- Evaluate Alerts ---

        // 1. T2 < -25
        if (isset($newState['t2_lt_minus_25_start'])) {
            $diffMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($newState['t2_lt_minus_25_start']));
            $severity = 0;
            if ($diffMinutes >= 30)
                $severity = 2;
            elseif ($diffMinutes >= 10)
                $severity = 1;

            if ($severity > 0) {
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_T2_BELOW_MINUS_25)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['val'] = $t2Val;
                $meta['duration'] = $diffMinutes;

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_T2_BELOW_MINUS_25],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 30 : 10;
                $occurredAt = \Carbon\Carbon::parse($newState['t2_lt_minus_25_start'])->addMinutes($thresholdMinutes);
                $this->handleEmailAlert($vendId, $alert, ['> 10 mins', '> 30 mins'], $occurredAt);
            }
        }

        // 2. T1 & T2 > 0
        if (isset($newState['t1_gt_0_start']) && isset($newState['t2_gt_0_start'])) {
            $s1 = \Carbon\Carbon::parse($newState['t1_gt_0_start']);
            $s2 = \Carbon\Carbon::parse($newState['t2_gt_0_start']);
            $effectiveStart = $s1->max($s2);
            $diffMinutes = $now->diffInMinutes($effectiveStart);

            $severity = 0;
            if ($diffMinutes >= 60)
                $severity = 2;
            elseif ($diffMinutes >= 30)
                $severity = 1;

            if ($severity > 0) {
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_TEMPS_ABOVE_0)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;
                $meta['v2'] = $t2Val;

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_0],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 60 : 30;
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $this->handleEmailAlert($vendId, $alert, ['> 30 mins', '> 60 mins'], $occurredAt);
            }
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_TEMPS_ABOVE_0)->update(['is_active' => false]);
        }

        // 3. T1 & T2 > -8
        if (isset($newState['t1_gt_minus_8_start']) && isset($newState['t2_gt_minus_8_start'])) {
            $s1 = \Carbon\Carbon::parse($newState['t1_gt_minus_8_start']);
            $s2 = \Carbon\Carbon::parse($newState['t2_gt_minus_8_start']);
            $effectiveStart = $s1->max($s2);
            $diffMinutes = $now->diffInMinutes($effectiveStart);

            $severity = 0;
            if ($diffMinutes >= 90)
                $severity = 2;
            elseif ($diffMinutes >= 60)
                $severity = 1;

            if ($severity > 0) {
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;
                $meta['v2'] = $t2Val;

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 90 : 60;
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $this->handleEmailAlert($vendId, $alert, ['> 60 mins', '> 90 mins'], $occurredAt);
            }
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8)->update(['is_active' => false]);
        }

        // 4. Not Reach -18 (Both > -18)
        if (isset($newState['t1_gt_minus_18_start']) && isset($newState['t2_gt_minus_18_start'])) {
            $s1 = \Carbon\Carbon::parse($newState['t1_gt_minus_18_start']);
            $s2 = \Carbon\Carbon::parse($newState['t2_gt_minus_18_start']);
            $effectiveStart = $s1->max($s2);
            $diffHours = $now->diffInHours($effectiveStart);

            $severity = 0;
            if ($diffHours >= 12)
                $severity = 2;
            elseif ($diffHours >= 8)
                $severity = 1;

            if ($severity > 0) {
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_NOT_REACH_MINUS_18)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_NOT_REACH_MINUS_18],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdHours = $severity === 2 ? 12 : 8;
                $occurredAt = $effectiveStart->copy()->addHours($thresholdHours);
                $this->handleEmailAlert($vendId, $alert, ['Within last 8 hours', '> 8 hours'], $occurredAt);
            }
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_NOT_REACH_MINUS_18)->update(['is_active' => false]);
        }
    }



    private function handleEmailAlert($vendId, $alert, $labels, $occurredAt = null)
    {
        // $alert->severity maps to index in $labels (Severity 1 = labels[0], Sev 2 = labels[1])
        // Indices are 0-based, severity is 1,2,3...
        $labelIndex = $alert->severity - 1;
        $label = $labels[$labelIndex] ?? 'Unknown';

        // Check if email/log already sent for THIS severity level
        $meta = $alert->meta_data;
        $lastSentSeverity = $meta['last_sent_severity'] ?? 0;

        if ($alert->severity > $lastSentSeverity) {
            $vend = \App\Models\Vend::find($vendId);
            if ($vend) {
                // 1. Send Email
                $alertService = app(\App\Services\AlertEmailService::class);
                $alertService->sendVendOperationErrorNotificationMail($vend, $alert->alert_type, $label);

                // 2. Log to Machine Log
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert', // Generic event
                    'subject' => $this->getHumanReadableAlertType($alert->alert_type) . " ({$label})",
                    'context' => [
                        'bucket' => $label,
                        'alert_type' => $alert->alert_type,
                        'severity' => $alert->severity,
                        'meta' => $meta,
                    ],
                    'occurred_at' => $occurredAt ?? now(),
                ]);

                // Update state
                $meta['last_sent_severity'] = $alert->severity;
                $alert->meta_data = $meta;
                $alert->is_email_alert_sent = true;
                $alert->email_alert_sent_at = now();
                $alert->save();
            }
        }
    }

    private function getHumanReadableAlertType($type)
    {
        return match ($type) {
            VendSmartAlert::TYPE_T2_BELOW_MINUS_25 => 'T2 below -25°C',
            VendSmartAlert::TYPE_TEMPS_ABOVE_0 => 'T1 & T2 above 0°C',
            VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8 => 'T1 & T2 above -8°C',
            VendSmartAlert::TYPE_NOT_REACH_MINUS_18 => 'T1 & T2 not reach -18°C',
            default => $type,
        };
    }

    private function checkConnectivity($vendId): void
    {
        $vend = \App\Models\Vend::find($vendId);
        if (!$vend) {
            \Log::info("Vend $vendId not found in checkConnectivity");
            return;
        }

        // Calculate hours offline
        $now = now();
        $limit = 500;
        $fallbackDate = '1970-01-01 00:00:00';

        $dates = [
            $vend->mqtt_last_updated_at,
            $vend->last_updated_at,
            $vend->last_vend_transaction_at,
            $vend->offline_restart_count_datetime,
        ];

        $lastContact = null;
        foreach ($dates as $date) {
            if ($date) {
                $d = \Carbon\Carbon::parse($date);
                if (!$lastContact || $d->gt($lastContact)) {
                    $lastContact = $d;
                }
            }
        }

        if (!$lastContact)
            return; // Never contacted?

        $hoursOffline = abs($now->diffInMinutes($lastContact)) / 60;

        // Determine Bucket
        $bucket = null;
        if ($hoursOffline >= 0.25 && $hoursOffline < 1) {
            $bucket = '< 1hr';
        } elseif ($hoursOffline >= 1 && $hoursOffline < 2) {
            $bucket = '< 2hr';
        } elseif ($hoursOffline >= 2 && $hoursOffline < 4) {
            $bucket = '< 4hr';
        } elseif ($hoursOffline >= 4 && $hoursOffline < 8) {
            $bucket = '< 8hr';
        } elseif ($hoursOffline >= 8 && $hoursOffline < 12) {
            $bucket = '< 12hr';
        } elseif ($hoursOffline >= 12) {
            $bucket = '> 12hr';
        }

        if (!$bucket)
            return;

        // Check state to avoid duplicate logs for the same bucket
        $state = $vend->temp_monitoring_state ?? [];
        $lastBucket = $state['connectivity_last_bucket'] ?? null;


        $threshold = match ($bucket) {
            '< 1hr' => 0.25,
            '< 2hr' => 1,
            '< 4hr' => 2,
            '< 8hr' => 4,
            '< 12hr' => 8,
            '> 12hr' => 12,
            default => 0,
        };
        $occurredAt = $lastContact->copy()->addMinutes($threshold * 60);

        if ($bucket !== $lastBucket) {
            // Log it
            VendLog::create([
                'vend_id' => $vend->id,
                'event' => 'machine_health_alert',
                'subject' => "Offline ({$bucket})",
                'context' => [
                    'bucket' => $bucket,
                    'type' => 'connectivity',
                    'hours_offline' => $hoursOffline,
                ],
                'occurred_at' => $occurredAt,
            ]);

            // Update state
            $state['connectivity_last_bucket'] = $bucket;
            $vend->temp_monitoring_state = $state;
            $vend->save();
        }
    }
    private function findMinTimestamp($vendId, $type, $start, $end, $value): string
    {
        $record = VendTemp::where('vend_id', $vendId)
            ->where('type', $type)
            ->whereBetween('created_at', [$start, $end])
            ->where('value', $value)
            ->orderBy('created_at', 'desc')
            ->first();

        return $record ? $record->created_at->toIso8601String() : $end->toIso8601String();
    }
}
