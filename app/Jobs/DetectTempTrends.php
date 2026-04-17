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
        // Keep all jobs on the low queue to not starve real-time queues
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->targetVendId) {
            \App\Models\Vend::where('is_active', true)
                ->where('is_testing', false)
                ->select('id')
                ->chunk(100, function ($vends) {
                    foreach ($vends as $vend) {
                        self::dispatch($vend->id, true);
                    }
                });
            return;
        }

        $vend = \App\Models\Vend::find($this->targetVendId);
        if (!$vend || !$vend->is_active || $vend->is_testing) {
            return;
        }

        // Pre-fetch all existing smart alerts for this vend in one query
        // so individual methods don't need to hit the DB per alert type.
        $existingAlerts = VendSmartAlert::where('vend_id', $vend->id)
            ->get()
            ->keyBy('alert_type');

        $this->analyzeStateful($vend, $existingAlerts);
        $this->checkConnectivity($vend);

        if ($this->isFullScan) {
            $this->analyzeTrend($vend, VendTemp::TYPE_CHAMBER, VendSmartAlert::TYPE_RISING_T1, $existingAlerts);
            $this->analyzeTrend($vend, VendTemp::TYPE_EVAPORATOR, VendSmartAlert::TYPE_RISING_T2, $existingAlerts);
            $this->checkNoTransactions($vend);
            $this->checkStockouts($vend);
            $this->analyzeFrozenT2($vend, $existingAlerts);
            $this->analyzePreventiveMaintenance($vend, $existingAlerts);
            $this->updateT1Lowest48h($vend);
        }

        if ($vend->isDirty()) {
            $vend->save();
        }
    }

    /**
     * Compute and store the lowest raw T1 value in the last 48 hours.
     * Stores raw integer (divide by 10 for °C). Sets NULL when no data exists.
     */
    private function updateT1Lowest48h(\App\Models\Vend $vend): void
    {
        $lowest = DB::table('vend_temps')
            ->where('vend_id', $vend->id)
            ->where('type', VendTemp::TYPE_CHAMBER)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->where('created_at', '>=', now()->subHours(48))
            ->min('value');

        $vend->t1_lowest_48h = $lowest !== null ? (int) $lowest : null;
    }

    private function analyzeFrozenT2(\App\Models\Vend $vend, $existingAlerts): void
    {
        if (!in_array($vend->menu_frame_id, [5, 6, 7, 10])) {
            return;
        }

        $scale = 10;
        $thresholdRaw = 2 * $scale;
        $now = now();
        $calc72h = $now->copy()->subHours(72);
        $window24 = $now->copy()->subHours(24);
        $window48 = $now->copy()->subHours(48);

        // 1. Single Aggregate Query for 24h/48h/72h metrics
        // AND timestamps for when it was frozen (value <= thresholdRaw)
        $stats = VendTemp::where('vend_id', $vend->id)
            ->where('type', VendTemp::TYPE_EVAPORATOR)
            ->where('created_at', '>=', $calc72h)
            ->selectRaw('
                    MAX(CASE WHEN created_at >= ? THEN value END) as max_24,
                    MIN(CASE WHEN created_at >= ? THEN value END) as min_24,
                    MAX(CASE WHEN created_at >= ? THEN value END) as max_48,
                    MAX(value) as max_72,
                    MAX(CASE WHEN value <= ? AND created_at >= ? THEN created_at END) as latest_frozen_24,
                    MAX(CASE WHEN value <= ? AND created_at >= ? THEN created_at END) as latest_frozen_48,
                    MAX(CASE WHEN value <= ? THEN created_at END) as latest_frozen_72
                ', [
                $window24,
                $window24,
                $window48,
                $thresholdRaw,
                $window24,
                $thresholdRaw,
                $window48,
                $thresholdRaw
            ])
            ->first();

        // Already checked error status above. If no stats, just return.
        if (!$stats)
            return;

        $max24 = $stats->max_24;
        $min24 = $stats->min_24;

        $dismissThresholdRaw = -23.5 * $scale;
        if ($max24 === null || $max24 > $thresholdRaw || ($min24 !== null && $min24 <= $dismissThresholdRaw)) {
            $this->resolveStatefulAlert($vend->id, VendSmartAlert::TYPE_T2_FROZEN, ['> 24 hr', '> 48 hr', '> 72 hr'], $existingAlerts);
            return;
        }

        $max48 = $stats->max_48;
        $max72 = $stats->max_72;

        $durationLabel = '> 24 hr';
        $severity = 1;
        $metaMax = $max24;
        $latestFrozenAt = $stats->latest_frozen_24;

        if ($max72 !== null && $max72 <= $thresholdRaw) {
            $durationLabel = '> 72 hr';
            $severity = 3;
            $metaMax = $max72;
            $latestFrozenAt = $stats->latest_frozen_72;
        } elseif ($max48 !== null && $max48 <= $thresholdRaw) {
            $durationLabel = '> 48 hr';
            $severity = 2;
            $metaMax = $max48;
            $latestFrozenAt = $stats->latest_frozen_48;
        }

        $minTimestamp = $latestFrozenAt ? \Carbon\Carbon::parse($latestFrozenAt)->toIso8601String() : $now->toIso8601String();

        $existing = $existingAlerts->get(VendSmartAlert::TYPE_T2_FROZEN);
        $meta = $existing ? ($existing->meta_data ?? []) : [];
        $baseHours = 24;
        if ($severity === 3)
            $baseHours = 72;
        elseif ($severity === 2)
            $baseHours = 48;

        $startedAt = $now->copy()->subHours($baseHours)->toIso8601String();
        if ($existing && $existing->is_active && isset($existing->meta_data['started_at'])) {
            $startedAt = $existing->meta_data['started_at'];
        }

        $meta['val'] = $metaMax / $scale;
        $meta['max_temp'] = $metaMax / $scale;
        $meta['duration_label'] = $durationLabel;
        $meta['calculated_at'] = $now->toIso8601String();
        $meta['started_at'] = $startedAt;
        $meta['min_timestamp'] = $minTimestamp;

        $alert = VendSmartAlert::updateOrCreate(
            ['vend_id' => $vend->id, 'alert_type' => VendSmartAlert::TYPE_T2_FROZEN],
            [
                'severity' => $severity,
                'is_active' => true,
                'meta_data' => $meta,
            ]
        );
        $this->logDashboardAlert($vend->id, $alert, ['> 24 hr', '> 48 hr', '> 72 hr']);
    }

    private function clearSmartAlert($vendId, $type)
    {
        VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $type)->where('is_active', true)->update(['is_active' => false]);
    }

    private function analyzePreventiveMaintenance(\App\Models\Vend $vend, $existingAlerts): void
    {
        $scale = 10;
        $now = now();
        $calc24h = $now->copy()->subHours(24);
        $calc72h = $now->copy()->subHours(72);

        // Combined query for both 24h and 72h ranges for chamber AND evaporator
        $stats = VendTemp::where('vend_id', $vend->id)
            ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
            ->where('created_at', '>=', $calc72h)
            ->selectRaw('
                type,
                MIN(CASE WHEN created_at >= ? THEN value END) as min_24,
                MIN(value) as min_72
            ', [$calc24h])
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $this->checkLowestStats($vend->id, 24, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_24H_ABOVE, $scale, $existingAlerts, [
            VendTemp::TYPE_CHAMBER => $stats->get(VendTemp::TYPE_CHAMBER)?->min_24,
            VendTemp::TYPE_EVAPORATOR => $stats->get(VendTemp::TYPE_EVAPORATOR)?->min_24
        ]);

        $this->checkLowestStats($vend->id, 72, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_72H_ABOVE, $scale, $existingAlerts, [
            VendTemp::TYPE_CHAMBER => $stats->get(VendTemp::TYPE_CHAMBER)?->min_72,
            VendTemp::TYPE_EVAPORATOR => $stats->get(VendTemp::TYPE_EVAPORATOR)?->min_72
        ]);
    }

    private function checkLowestStats($vendId, $hours, $thresholds, $alertType, $scale, $existingAlerts, $minValues)
    {
        $minT1 = $minValues[VendTemp::TYPE_CHAMBER] ?? null;
        $minT2 = $minValues[VendTemp::TYPE_EVAPORATOR] ?? null;

        if ($minT1 === null || $minT2 === null)
            return;

        $v1 = $minT1 / $scale;
        $v2 = $minT2 / $scale;

        $sev = 0;
        if ($v1 > $thresholds[2] && $v2 > $thresholds[2])
            $sev = 3;
        elseif ($v1 > $thresholds[1] && $v2 > $thresholds[1])
            $sev = 2;
        elseif ($v1 > $thresholds[0] && $v2 > $thresholds[0])
            $sev = 1;

        if ($sev > 0) {
            $activeThreshold = $thresholds[$sev - 1];
            $threshRaw = $activeThreshold * $scale;

            // Date of last good status (only check if an alert is triggered)
            $lastGood = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('value', '<=', $threshRaw)
                ->where('created_at', '>=', now()->subDays(14))
                ->latest('created_at')
                ->value('created_at');

            $duration = $lastGood
                ? now()->diffInMinutes($lastGood)
                : $hours * 60;

            // Find timestamp of T1's min value in the window
            $minT1At = VendTemp::where('vend_id', $vendId)
                ->where('type', VendTemp::TYPE_CHAMBER)
                ->where('created_at', '>=', now()->subHours($hours))
                ->where('value', $minT1)
                ->latest('created_at')
                ->value('created_at');

            $minTimestamp = $minT1At ? \Carbon\Carbon::parse($minT1At)->toIso8601String() : now()->toIso8601String();

            $existing = $existingAlerts->get($alertType);
            $meta = $existing ? ($existing->meta_data ?? []) : [];
            $meta['min_t1'] = $v1;
            $meta['min_t2'] = $v2;
            $meta['duration'] = $duration;
            $meta['started_at'] = now()->subMinutes($duration)->toIso8601String();
            $meta['min_timestamp'] = $minTimestamp;

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => $meta]
            );
            $this->logDashboardAlert($vendId, $alert, ['Above -21c', 'Above -20c', 'Above -19c']);
        } else {
            $this->resolveStatefulAlert($vendId, $alertType, ['Above -21c', 'Above -20c', 'Above -19c'], $existingAlerts);
        }
    }

    private function analyzeTrend(\App\Models\Vend $vend, int $tempType, string $alertType, $existingAlerts): void
    {
        $now = now();
        $windowCurrentStart = $now->copy()->subHours(24);
        $windowPrevStart = $now->copy()->subHours(48);

        // Optimized: Instead of hydrating thousands of models and sorting in PHP,
        // we query for the lowest records directly from the database to reduce memory and execution time.
        $currRecord = VendTemp::where('vend_id', $vend->id)
            ->where('type', $tempType)
            ->where('created_at', '>=', $windowCurrentStart)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->orderBy('value', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();

        $prevRecord = VendTemp::where('vend_id', $vend->id)
            ->where('type', $tempType)
            ->where('created_at', '>=', $windowPrevStart)
            ->where('created_at', '<', $windowCurrentStart)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->orderBy('value', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();

        $curr = $currRecord?->value;
        $prev = $prevRecord?->value;

        if (is_null($curr) || is_null($prev) || abs($curr - 32767) < 1 || abs($prev - 32767) < 1) {
            $this->resolveStatefulAlert($vend->id, $alertType, ['Δ ≥ 1c', 'Δ ≥ 2c', 'Δ ≥ 3c'], $existingAlerts);
            return;
        }

        $scale = 10;
        $deltaC = ($curr - $prev) / $scale;

        if ($deltaC >= 1.0) {
            $severity = 1;
            if ($deltaC >= 3.0)
                $severity = 3;
            elseif ($deltaC >= 2.0)
                $severity = 2;

            $existingAlert = $existingAlerts->get($alertType);
            $firstDetectionTime = $now->toIso8601String();
            if ($existingAlert && $existingAlert->is_active && isset($existingAlert->meta_data['first_detection_at'])) {
                $firstDetectionTime = $existingAlert->meta_data['first_detection_at'];
            }

            $elapsedMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($firstDetectionTime));
            $duration = (24 * 60) + $elapsedMinutes;

            $meta = $existingAlert ? ($existingAlert->meta_data ?? []) : [];
            $meta['current_min'] = $curr / $scale;
            $meta['prev_min'] = $prev / $scale;
            $meta['delta'] = $deltaC;
            $meta['calculated_at'] = $now->toIso8601String();
            $meta['first_detection_at'] = $firstDetectionTime;
            $meta['started_at'] = \Carbon\Carbon::parse($firstDetectionTime)->subHours(24)->toIso8601String();
            $meta['duration'] = $duration;
            $meta['min_timestamp'] = $currRecord->created_at->toIso8601String();
            $meta['prev_min_timestamp'] = $prevRecord->created_at->toIso8601String();

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vend->id, 'alert_type' => $alertType],
                [
                    'severity' => $severity,
                    'is_active' => true,
                    'meta_data' => $meta,
                ]
            );
            $this->logDashboardAlert($vend->id, $alert, ['Δ ≥ 1c', 'Δ ≥ 2c', 'Δ ≥ 3c']);
        } else {
            $this->resolveStatefulAlert($vend->id, $alertType, ['Δ ≥ 1c', 'Δ ≥ 2c', 'Δ ≥ 3c'], $existingAlerts);
        }
    }

    private function analyzeStateful(\App\Models\Vend $vend, $existingAlerts): void
    {
        $vendId = $vend->id;

        // Single query to get the two latest temps (both types)
        $latestTemps = VendTemp::where('vend_id', $vendId)
            ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
            ->orderBy('created_at', 'desc')
            ->take(10) // Small buffer to ensure we get both types
            ->get();

        $t1 = $latestTemps->where('type', VendTemp::TYPE_CHAMBER)->first();
        $t2 = $latestTemps->where('type', VendTemp::TYPE_EVAPORATOR)->first();

        if (!$t1 || !$t2)
            return;

        $t1Val = $t1->value / 10;
        $t2Val = $t2->value / 10;
        $now = now();

        $state = $vend->temp_monitoring_state ?? [];
        $newState = $state;
        unset($newState['t1_higher_t2_start']);

        // Resolve any lingering T1 higher than T2 alerts if they exist
        if ($existingAlerts->has(VendSmartAlert::TYPE_T1_HIGHER_THAN_T2)) {
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_T1_HIGHER_THAN_T2, ['> 10 mins', '> 30 mins'], $existingAlerts);
        }

        // --- Logic: 1B) Compressor & or Fan OFF ---
        // Skip entirely for machines without a fan signal (is_fan_enabled = false → "N/A" badge in CustomerIndex)
        if (!$vend->is_fan_enabled) {
            // Ensure any lingering alert is cleared immediately for fan-less machines
            unset($newState['comp_fan_off_start']);
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_COMP_FAN_OFF, ['> 40 mins', '> 60 mins'], $existingAlerts);
        } else {
            $latestFan = \App\Models\VendFan::where('vend_id', $vendId)->where('type', \App\Models\VendFan::TYPE_MAIN)->orderBy('created_at', 'desc')->first();

            $fanIsOff = false;
            $startOff = $now;
            if ($latestFan) {
                $recentActiveFan = \App\Models\VendFan::where('vend_id', $vendId)
                    ->where('type', \App\Models\VendFan::TYPE_MAIN)
                    ->where('value', '>', 0)
                    ->where('created_at', '>=', $now->copy()->subHours(8))
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($recentActiveFan) {
                    $fanIsOff = $now->diffInMinutes($recentActiveFan->created_at) > 40;
                    $startOff = $recentActiveFan->created_at;
                } else {
                    $fanIsOff = true;
                    $startOff = $now->copy()->subHours(8); // Bound backtrace history to 8 hours to avoid db full table scan
                }
            }

            if ($fanIsOff && $vend->is_online) {
                if (!isset($state['comp_fan_off_start'])) {
                    $newState['comp_fan_off_start'] = $startOff->toIso8601String();
                }
            } else {
                unset($newState['comp_fan_off_start']);
                $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_COMP_FAN_OFF, ['> 40 mins', '> 60 mins'], $existingAlerts);
            }
        }

        // --- Logic: T1 & T2 > 0 (Warm) ---
        if ($t1Val > 0 && $t1Val < 100 && $t1->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t1_gt_0_start']))
                $newState['t1_gt_0_start'] = $t1->created_at->toIso8601String();
        } else {
            unset($newState['t1_gt_0_start']);
        }
        if ($t2Val > 0 && $t2Val < 100 && $t2->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t2_gt_0_start']))
                $newState['t2_gt_0_start'] = $t2->created_at->toIso8601String();
        } else {
            unset($newState['t2_gt_0_start']);
        }

        // --- Logic: T1 & T2 > -8 (Semi-Warm) ---
        if ($t1Val > -8 && $t1Val < 100 && $t1->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t1_gt_minus_8_start']))
                $newState['t1_gt_minus_8_start'] = $t1->created_at->toIso8601String();
        } else {
            unset($newState['t1_gt_minus_8_start']);
        }
        if ($t2Val > -8 && $t2Val < 100 && $t2->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t2_gt_minus_8_start']))
                $newState['t2_gt_minus_8_start'] = $t2->created_at->toIso8601String();
        } else {
            unset($newState['t2_gt_minus_8_start']);
        }

        // --- Logic: Not Reached -18 (Both > -18) ---
        if ($t1Val > -18 && $t1Val < 100 && $t1->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t1_gt_minus_18_start']))
                $newState['t1_gt_minus_18_start'] = $t1->created_at->toIso8601String();
        } else {
            unset($newState['t1_gt_minus_18_start']);
        }
        if ($t2Val > -18 && $t2Val < 100 && $t2->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t2_gt_minus_18_start']))
                $newState['t2_gt_minus_18_start'] = $t2->created_at->toIso8601String();
        } else {
            unset($newState['t2_gt_minus_18_start']);
        }

        // --- Logic: 2E) T1 or T2 > -17 and upward trending ---
        $isT1Above17 = ($t1Val > -17 && $t1Val < 100 && $t1->value != VendTemp::TEMPERATURE_ERROR);
        $isT2Above17 = ($t2Val > -17 && $t2Val < 100 && $t2->value != VendTemp::TEMPERATURE_ERROR);

        if ($isT1Above17 || $isT2Above17) {
            foreach (['t1', 't2'] as $prefix) {
                $isAbove = ${'is' . ucfirst($prefix) . 'Above17'};
                $val = ${$prefix . 'Val'};
                if ($isAbove) {
                    $lastVal = $state[$prefix . '_upward_last_val'] ?? null;
                    if ($lastVal !== null && $val > $lastVal) {
                        $newState[$prefix . '_upward_streak'] = ($state[$prefix . '_upward_streak'] ?? 0) + 1;
                    } else if ($lastVal !== null && $val < $lastVal) {
                        $newState[$prefix . '_upward_streak'] = 0;
                    }
                    $newState[$prefix . '_upward_last_val'] = $val;
                } else {
                    unset($newState[$prefix . '_upward_streak'], $newState[$prefix . '_upward_last_val']);
                }
            }
        } else {
            unset($newState['t1_upward_streak'], $newState['t1_upward_last_val']);
            unset($newState['t2_upward_streak'], $newState['t2_upward_last_val']);
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_17_UPWARD, ['> 30 mins', '> 50 mins'], $existingAlerts);
        }

        // Save State
        if ($state !== $newState) {
            $vend->temp_monitoring_state = $newState;
        }

        // --- Evaluate Alerts with Priority Suppression ---
        // 1B. Compressor & or Fan OFF (Independent)
        if (isset($newState['comp_fan_off_start']) && $vend->is_online) {
            $diffMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($newState['comp_fan_off_start']), true);
            $severity = 0;
            if ($diffMinutes >= 60)
                $severity = 2;
            elseif ($diffMinutes >= 40)
                $severity = 1;

            if ($severity > 0) {
                $existing = $existingAlerts->get(VendSmartAlert::TYPE_COMP_FAN_OFF);
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['duration'] = $diffMinutes;
                $meta['started_at'] = $newState['comp_fan_off_start'];

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_COMP_FAN_OFF],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 60 : 40;
                $effectiveStart = \Carbon\Carbon::parse($newState['comp_fan_off_start']);
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(40);
                $this->handleEmailAlert($vend, $alert, ['> 40 mins', '> 60 mins'], $occurredAt, $firstTriggerAt);
            }
        }

        // Initialize flags for alert priorities
        $isCaseBActive = false;
        $isCaseCActive = false;

        // 2. T1 & T2 > 0 (Priority 1 for Warm)
        if (isset($newState['t1_gt_0_start']) && isset($newState['t2_gt_0_start'])) {
            $s1 = \Carbon\Carbon::parse($newState['t1_gt_0_start']);
            $s2 = \Carbon\Carbon::parse($newState['t2_gt_0_start']);
            $effectiveStart = $s1->max($s2);
            $diffMinutes = $now->diffInMinutes($effectiveStart, true);

            $severity = 0;
            if ($diffMinutes >= 50)
                $severity = 2;
            elseif ($diffMinutes >= 30)
                $severity = 1;

            if ($severity > 0) {
                $isCaseBActive = true;
                $existing = $existingAlerts->get(VendSmartAlert::TYPE_TEMPS_ABOVE_0);
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;
                $meta['v2'] = $t2Val;
                $meta['started_at'] = $effectiveStart->toIso8601String();

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_0],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 50 : 30;
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(30);
                $this->handleEmailAlert($vend, $alert, ['> 30 mins', '> 50 mins'], $occurredAt, $firstTriggerAt);
            }
        } else {
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_TEMPS_ABOVE_0, ['> 30 mins', '> 50 mins'], $existingAlerts);
        }

        // 3. T1 & T2 > -8 (Priority 2 for Warm) - SUPPRESS if Case B is active
        if (!$isCaseBActive && isset($newState['t1_gt_minus_8_start']) && isset($newState['t2_gt_minus_8_start'])) {
            $s1 = \Carbon\Carbon::parse($newState['t1_gt_minus_8_start']);
            $s2 = \Carbon\Carbon::parse($newState['t2_gt_minus_8_start']);
            $effectiveStart = $s1->max($s2);
            $diffMinutes = $now->diffInMinutes($effectiveStart, true);

            $severity = 0;
            if ($diffMinutes >= 90)
                $severity = 2;
            elseif ($diffMinutes >= 60)
                $severity = 1;

            if ($severity > 0) {
                $isCaseCActive = true;
                $existing = $existingAlerts->get(VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8);
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;
                $meta['v2'] = $t2Val;
                $meta['started_at'] = $effectiveStart->toIso8601String();

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 90 : 60;
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(60);
                $this->handleEmailAlert($vend, $alert, ['> 60 mins', '> 90 mins'], $occurredAt, $firstTriggerAt);
            }
        } else {
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8, ['> 60 mins', '> 90 mins'], $existingAlerts);
        }

        // 4. Not Reach -18 (Priority 3 for Warm) - SUPPRESS if Case B or C is active
        if (!$isCaseBActive && !$isCaseCActive && isset($newState['t1_gt_minus_18_start']) && isset($newState['t2_gt_minus_18_start'])) {
            $s1 = \Carbon\Carbon::parse($newState['t1_gt_minus_18_start']);
            $s2 = \Carbon\Carbon::parse($newState['t2_gt_minus_18_start']);
            $effectiveStart = $s1->max($s2);
            $diffHours = $now->diffInHours($effectiveStart, true);

            $severity = 0;
            if ($diffHours >= 12)
                $severity = 2;
            elseif ($diffHours >= 8)
                $severity = 1;

            if ($severity > 0) {
                $existing = $existingAlerts->get(VendSmartAlert::TYPE_NOT_REACH_MINUS_18);
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;
                $meta['started_at'] = $effectiveStart->toIso8601String();

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_NOT_REACH_MINUS_18],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdHours = $severity === 2 ? 12 : 8;
                $occurredAt = $effectiveStart->copy()->addHours($thresholdHours);
                $firstTriggerAt = $effectiveStart->copy()->addHours(8);
                $this->handleEmailAlert($vend, $alert, ['Within last 8 hours', '> 8 hours'], $occurredAt, $firstTriggerAt);
            }
        } else {
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_NOT_REACH_MINUS_18, ['Within last 8 hours', '> 8 hours'], $existingAlerts);
        }

        // 5. T1 or T2 > -17 and upward trending (2E)
        $maxStreak = max($newState['t1_upward_streak'] ?? 0, $newState['t2_upward_streak'] ?? 0);
        if ($maxStreak >= 3) {
            $severity = ($maxStreak >= 5) ? 2 : 1;
            $startTime = $now->copy()->subMinutes($maxStreak * 10)->toIso8601String();

            $existing = $existingAlerts->get(VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_17_UPWARD);
            $meta = $existing ? ($existing->meta_data ?? []) : [];
            $meta['v1'] = $t1Val;
            $meta['v2'] = $t2Val;
            $meta['streak'] = $maxStreak;
            $meta['started_at'] = $startTime;
            $meta['duration'] = $maxStreak * 10;

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_17_UPWARD],
                ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
            );
            $thresholdMinutes = $severity === 2 ? 50 : 30;
            $occurredAt = $now;
            $firstTriggerAt = \Carbon\Carbon::parse($startTime);
            $this->handleEmailAlert($vend, $alert, ['> 30 mins', '> 50 mins'], $occurredAt, $firstTriggerAt);
        }
    }



    private function handleEmailAlert($vend, $alert, $labels, $occurredAt = null, $originalTriggerAt = null)
    {
        $labelIndex = $alert->severity - 1;
        $label = $labels[$labelIndex] ?? 'Unknown';

        $meta = $alert->meta_data;
        $lastSentSeverity = $meta['last_sent_severity'] ?? 0;

        if ($alert->severity > $lastSentSeverity) {
            if ($vend) {
                if ($lastSentSeverity > 0) {
                    $oldBuckets = array_slice($labels, 0, $alert->severity - 1);
                    if (!empty($oldBuckets)) {
                        VendLog::where('vend_id', $vend->id)
                            ->where('event', 'machine_health_alert')
                            ->whereIn('context->bucket', $oldBuckets)
                            ->where('context->alert_type', $alert->alert_type)
                            ->delete();
                        \App\Models\MachineHealthHistory::where('vend_id', $vend->id)
                            ->where('event', 'machine_health_alert')
                            ->whereIn('bucket', $oldBuckets)
                            ->where('alert_type', $alert->alert_type)
                            ->delete();
                    }
                }

                // 1. Send Email
                $alertService = app(\App\Services\AlertEmailService::class);
                $alertService->sendVendOperationErrorNotificationMail($vend, $alert->alert_type, $label);

                // 2. Log to Machine Log
                $logContext = [
                    'bucket' => $label,
                    'alert_type' => $alert->alert_type,
                    'severity' => $alert->severity,
                    'triggered_at' => $originalTriggerAt ? $originalTriggerAt->toIso8601String() : ($occurredAt ?? now())->toIso8601String(),
                    'started_at' => $meta['started_at'] ?? null,
                    'meta' => $meta,
                ];
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert',
                    'subject' => $this->getHumanReadableAlertType($alert->alert_type) . " ({$label})",
                    'context' => $logContext,
                    'occurred_at' => $occurredAt ?? now(),
                ]);
                \App\Models\MachineHealthHistory::log(
                    $vend->id,
                    'machine_health_alert',
                    $alert->alert_type,
                    $label,
                    $alert->severity,
                    $logContext,
                    $occurredAt
                );

                $meta['last_sent_severity'] = $alert->severity;
                $alert->meta_data = $meta;
                $alert->is_email_alert_sent = true;
                $alert->email_alert_sent_at = now();
                $alert->save();
            }
        }
    }

    private function logDashboardAlert(int $vendId, $alert, array $labels, $occurredAt = null, $originalTriggerAt = null): void
    {
        $labelIndex = $alert->severity - 1;
        $label = $labels[$labelIndex] ?? 'Unknown';

        $meta = $alert->meta_data;
        $lastSentSeverity = $meta['last_sent_severity'] ?? 0;

        if ($alert->severity > $lastSentSeverity) {
            if ($lastSentSeverity > 0) {
                $oldBuckets = array_slice($labels, 0, $alert->severity - 1);
                if (!empty($oldBuckets)) {
                    VendLog::where('vend_id', $vendId)
                        ->where('event', 'machine_health_alert')
                        ->whereIn('context->bucket', $oldBuckets)
                        ->where('context->alert_type', $alert->alert_type)
                        ->delete();
                    \App\Models\MachineHealthHistory::where('vend_id', $vendId)
                        ->where('event', 'machine_health_alert')
                        ->whereIn('bucket', $oldBuckets)
                        ->where('alert_type', $alert->alert_type)
                        ->delete();
                }
            }

            $logContext = [
                'bucket' => $label,
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'triggered_at' => $originalTriggerAt ? $originalTriggerAt->toIso8601String() : ($occurredAt ?? now())->toIso8601String(),
                'meta' => $meta,
            ];
            VendLog::create([
                'vend_id' => $vendId,
                'event' => 'machine_health_alert',
                'subject' => $this->getHumanReadableAlertType($alert->alert_type) . " ({$label})",
                'context' => $logContext,
                'occurred_at' => $occurredAt ?? now(),
            ]);
            \App\Models\MachineHealthHistory::log(
                $vendId,
                'machine_health_alert',
                $alert->alert_type,
                $label,
                $alert->severity,
                $logContext,
                $occurredAt
            );

            $meta['last_sent_severity'] = $alert->severity;
            $alert->meta_data = $meta;
            $alert->save();
        }
    }

    private function getHumanReadableAlertType($type)
    {
        return match ($type) {
            VendSmartAlert::TYPE_COMP_FAN_OFF => 'Compressor & or Fan OFF',
            VendSmartAlert::TYPE_TEMPS_ABOVE_0 => 'T1 & or T2 above 0°C',
            VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8 => 'T1 & or T2 above -8°C',
            VendSmartAlert::TYPE_NOT_REACH_MINUS_18 => 'T1 & or T2 did not reach -18°C',
            VendSmartAlert::TYPE_LOWEST_24H_ABOVE => 'T1 & T2 lowest (last 24hrs)',
            VendSmartAlert::TYPE_LOWEST_72H_ABOVE => 'T1 & T2 lowest (last 72hrs)',
            VendSmartAlert::TYPE_RISING_T1 => 'Rising lowest T1',
            VendSmartAlert::TYPE_RISING_T2 => 'Rising lowest T2',
            VendSmartAlert::TYPE_T2_FROZEN => 'T2, never above 2°C',
            VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_17_UPWARD => 'T1 or T2 above -17°C and upward trending',
            default => $type,
        };
    }

    private function checkConnectivity(\App\Models\Vend $vend): void
    {
        $now = now();

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
            return;

        $hoursOffline = abs($now->diffInMinutes($lastContact)) / 60;

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

        // Check if back online
        if (!$bucket && $hoursOffline < 0.25) {
            $state = $vend->temp_monitoring_state ?? [];
            $lastBucket = $state['connectivity_last_bucket'] ?? null;

            if ($lastBucket) {
                // Calculate lapse hours: from when the connectivity alert was first triggered until now
                $triggerLog = \App\Models\MachineHealthHistory::where('vend_id', $vend->id)
                    ->where('event', 'machine_health_alert')
                    ->where('alert_type', 'connectivity')
                    ->where('bucket', $lastBucket)
                    ->orderByDesc('occurred_at')
                    ->first();
                $lapseHours = $triggerLog
                    ? max(0, round(\Carbon\Carbon::parse($triggerLog->occurred_at)->diffInMinutes(now()) / 60, 2))
                    : null;

                $logContext = [
                    'bucket' => $lastBucket,
                    'type' => 'connectivity',
                    'lapse_hours' => $lapseHours,
                ];
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert_dismissed',
                    'subject' => "Back Online (was {$lastBucket})",
                    'context' => $logContext,
                    'occurred_at' => now(),
                ]);
                \App\Models\MachineHealthHistory::log(
                    $vend->id,
                    'machine_health_alert_dismissed',
                    'connectivity',
                    $lastBucket,
                    null,
                    $logContext
                );

                unset($state['connectivity_last_bucket']);
                $vend->temp_monitoring_state = $state;
            }
            return;
        }

        if (!$bucket)
            return;

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
            if ($lastBucket) {
                VendLog::where('vend_id', $vend->id)
                    ->where('event', 'machine_health_alert')
                    ->where('context->bucket', $lastBucket)
                    ->where('context->type', 'connectivity')
                    ->delete();
                \App\Models\MachineHealthHistory::where('vend_id', $vend->id)
                    ->where('event', 'machine_health_alert')
                    ->where('bucket', $lastBucket)
                    ->where('alert_type', 'connectivity')
                    ->delete();
            }

            $logContext = [
                'bucket' => $bucket,
                'type' => 'connectivity',
                'hours_offline' => $hoursOffline,
                'triggered_at' => $lastContact ? $lastContact->copy()->addMinutes(15)->toIso8601String() : $occurredAt->toIso8601String(),
            ];
            VendLog::create([
                'vend_id' => $vend->id,
                'event' => 'machine_health_alert',
                'subject' => "Offline ({$bucket})",
                'context' => $logContext,
                'occurred_at' => $occurredAt,
            ]);
            \App\Models\MachineHealthHistory::log(
                $vend->id,
                'machine_health_alert',
                'connectivity',
                $bucket,
                null,
                $logContext,
                $occurredAt
            );

            $state['connectivity_last_bucket'] = $bucket;
            $vend->temp_monitoring_state = $state;
            $vend->save();
        }
    }

    private function checkNoTransactions(\App\Models\Vend $vend): void
    {
        $now = now();
        $state = $vend->temp_monitoring_state ?? [];
        $lastNoTxnBuckets = $state['no_txn_buckets'] ?? [];
        $newNoTxnBuckets = [];

        // Any Sales (48hr)
        if ($vend->last_vend_transaction_at) {
            $diff = \Carbon\Carbon::parse($vend->last_vend_transaction_at)->diffInMinutes($now) / 60;
            if ($diff >= 48) {
                $newNoTxnBuckets['any'] = ['label' => 'Any Sales', 'hours' => 48, 'diff' => $diff];
            }
        }

        // Cash Sales (48hr)
        $paramJson = (array) ($vend->parameter_json ?? []);
        if (isset($paramJson['BILLStat']) && $paramJson['BILLStat'] == 3 && $vend->last_cash_vend_transaction_at) {
            $diff = \Carbon\Carbon::parse($vend->last_cash_vend_transaction_at)->diffInMinutes($now) / 60;
            if ($diff >= 48) {
                $newNoTxnBuckets['cash'] = ['label' => 'Cash Sales', 'hours' => 48, 'diff' => $diff];
            }
        }

        // Card Sales (48hr)
        if (isset($paramJson['CSHLStat']) && $paramJson['CSHLStat'] == 3 && $vend->last_card_vend_transaction_at) {
            $diff = \Carbon\Carbon::parse($vend->last_card_vend_transaction_at)->diffInMinutes($now) / 60;
            if ($diff >= 48) {
                $newNoTxnBuckets['card'] = ['label' => 'Card Terminal', 'hours' => 48, 'diff' => $diff];
            }
        }

        // QR (72hr)
        $paJson = (array) ($vend->acb_vmc_pa_json ?? []);
        if ($vend->is_txn_src && isset($paJson['QRCode']) && $paJson['QRCode'] == 1 && $vend->last_txn_src_at) {
            $diff = \Carbon\Carbon::parse($vend->last_txn_src_at)->diffInMinutes($now) / 60;
            if ($diff >= 72) {
                $newNoTxnBuckets['qr'] = ['label' => 'QR Sales', 'hours' => 72, 'diff' => $diff];
            }
        }

        // Digital Screen (72hr)
        if ($vend->is_txn_src && $vend->last_txn_src_at) {
            $diff = \Carbon\Carbon::parse($vend->last_txn_src_at)->diffInMinutes($now) / 60;
            if ($diff >= 72) {
                $newNoTxnBuckets['digitalscreen'] = ['label' => 'Digital Screen', 'hours' => 72, 'diff' => $diff];
            }
        }

        foreach ($newNoTxnBuckets as $key => $data) {
            if (!isset($lastNoTxnBuckets[$key])) {
                $logContext = [
                    'bucket' => ">= {$data['hours']}hr",
                    'type' => "no_txn_{$key}",
                    'hours_offline' => $data['diff'],
                ];
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert',
                    'subject' => "No {$data['label']} (>= {$data['hours']}hr)",
                    'context' => $logContext,
                    'occurred_at' => $now,
                ]);
                \App\Models\MachineHealthHistory::log(
                    $vend->id,
                    'machine_health_alert',
                    "no_txn_{$key}",
                    ">= {$data['hours']}hr",
                    null,
                    $logContext,
                    $now
                );
            }
        }

        foreach ($lastNoTxnBuckets as $key => $data) {
            if (!isset($newNoTxnBuckets[$key])) {
                $noTxnTriggerLog = \App\Models\MachineHealthHistory::where('vend_id', $vend->id)
                    ->where('event', 'machine_health_alert')
                    ->where('alert_type', "no_txn_{$key}")
                    ->orderByDesc('occurred_at')
                    ->first();
                $noTxnLapseHours = $noTxnTriggerLog
                    ? max(0, round(\Carbon\Carbon::parse($noTxnTriggerLog->occurred_at)->diffInMinutes($now) / 60, 2))
                    : null;

                $logContext = [
                    'bucket' => ">= {$data['hours']}hr",
                    'type' => "no_txn_{$key}",
                    'lapse_hours' => $noTxnLapseHours,
                ];
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert_dismissed',
                    'subject' => "Transaction Received ({$data['label']})",
                    'context' => $logContext,
                    'occurred_at' => $now,
                ]);
                \App\Models\MachineHealthHistory::log(
                    $vend->id,
                    'machine_health_alert_dismissed',
                    "no_txn_{$key}",
                    ">= {$data['hours']}hr",
                    null,
                    $logContext,
                    $now
                );
            }
        }

        if (json_encode($lastNoTxnBuckets) !== json_encode($newNoTxnBuckets)) {
            $state['no_txn_buckets'] = $newNoTxnBuckets;
            $vend->temp_monitoring_state = $state;
        }
    }

    private function checkStockouts(\App\Models\Vend $vend): void
    {
        $now = now();
        $targetHours = 72;
        $cutoff = $now->copy()->subHours($targetHours);

        $state = $vend->temp_monitoring_state ?? [];
        $lastStockoutBuckets = $state['stockout_buckets'] ?? [];
        $newStockoutBuckets = [];

        // Use joinSub to find the latest event's ID for each channel.
        // This is significantly faster than using whereIn with a subquery.
        $latestEvents = \App\Models\VendChannelStockEvent::where('vend_channel_stock_events.vend_id', $vend->id)
            ->joinSub(function ($sub) use ($vend) {
                $sub->select('vend_channel_id', DB::raw('MAX(id) as max_id'))
                    ->from('vend_channel_stock_events')
                    ->where('vend_id', $vend->id)
                    ->groupBy('vend_channel_id');
            }, 'latest', 'vend_channel_stock_events.id', '=', 'latest.max_id')
            ->where('event_type', \App\Models\VendChannelStockEvent::TYPE_SOLD_OUT)
            ->where('occurred_at', '<', $cutoff)
            ->with('vendChannel:id,code')
            ->get();

        foreach ($latestEvents as $event) {
            $durationHours = $event->occurred_at->diffInMinutes($now) / 60;
            $channelId = $event->vend_channel_id;
            $code = $event->vendChannel ? $event->vendChannel->code : 'Unknown';

            $newStockoutBuckets[$channelId] = [
                'channel_code' => $code,
                'hours' => $targetHours,
                'diff' => $durationHours,
            ];
        }

        foreach ($newStockoutBuckets as $channelId => $data) {
            if (!isset($lastStockoutBuckets[$channelId])) {
                $logContext = [
                    'bucket' => ">= {$data['hours']}hr",
                    'type' => "stockout_channel_{$channelId}",
                    'hours_offline' => $data['diff'],
                ];
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert',
                    'subject' => "Stockout Channel {$data['channel_code']} (>= {$data['hours']}hr)",
                    'context' => $logContext,
                    'occurred_at' => $now,
                ]);
                \App\Models\MachineHealthHistory::log(
                    $vend->id,
                    'machine_health_alert',
                    "stockout_channel_{$channelId}",
                    ">= {$data['hours']}hr",
                    null,
                    $logContext,
                    $now
                );
            }
        }

        foreach ($lastStockoutBuckets as $channelId => $data) {
            if (!isset($newStockoutBuckets[$channelId])) {
                $logContext = [
                    'bucket' => ">= {$data['hours']}hr",
                    'type' => "stockout_channel_{$channelId}",
                ];
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert_dismissed',
                    'subject' => "Restocked Channel {$data['channel_code']}",
                    'context' => $logContext,
                    'occurred_at' => $now,
                ]);
                \App\Models\MachineHealthHistory::log(
                    $vend->id,
                    'machine_health_alert_dismissed',
                    "stockout_channel_{$channelId}",
                    ">= {$data['hours']}hr",
                    null,
                    $logContext,
                    $now
                );
            }
        }

        if (json_encode($lastStockoutBuckets) !== json_encode($newStockoutBuckets)) {
            $state['stockout_buckets'] = $newStockoutBuckets;
            $vend->temp_monitoring_state = $state;
        }
    }

    private function resolveStatefulAlert(int $vendId, string $alertType, array $labels, $existingAlerts = null): void
    {
        // Use pre-fetched alerts if provided, else fall back to a DB query
        $alert = $existingAlerts
            ? $existingAlerts->get($alertType)
            : VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->first();

        if ($alert && $alert->is_active) {
            $meta = $alert->meta_data;
            $lastSent = $meta['last_sent_severity'] ?? $alert->severity;
            unset($meta['last_sent_severity']); // Reset so next trigger logs again
            $alert->update(['is_active' => false, 'meta_data' => $meta]);

            if ($lastSent > count($labels))
                $lastSent = count($labels);

            if ($lastSent > 0) {
                $labelIndex = $lastSent - 1;
                if (isset($labels[$labelIndex])) {
                    $bucket = $labels[$labelIndex];

                    // Compute lapse: from when this alert was first triggered to now
                    $now = now();
                    $startTime = $meta['started_at'] ?? $meta['min_timestamp'] ?? $meta['triggered_at'] ?? null;
                    $smartLapseHours = null;

                    if ($startTime) {
                        try {
                            $smartLapseHours = round(\Carbon\Carbon::parse($startTime)->diffInMinutes($now) / 60, 2);
                        } catch (\Exception $e) {
                        }
                    }

                    if ($smartLapseHours === null) {
                        $smartTriggerLog = \App\Models\MachineHealthHistory::where('vend_id', $vendId)
                            ->where('event', 'machine_health_alert')
                            ->where('alert_type', $alertType)
                            ->orderByDesc('occurred_at')
                            ->first();
                        $smartLapseHours = $smartTriggerLog
                            ? max(0, round(\Carbon\Carbon::parse($smartTriggerLog->occurred_at)->diffInMinutes($now) / 60, 2))
                            : null;
                    }

                    $smartLapseHours = $smartLapseHours !== null ? max(0, (float) $smartLapseHours) : null;

                    $logContext = [
                        'bucket' => $bucket,
                        'alert_type' => $alertType,
                        'severity' => $lastSent,
                        'lapse_hours' => $smartLapseHours,
                    ];
                    VendLog::create([
                        'vend_id' => $vendId,
                        'event' => 'machine_health_alert_dismissed',
                        'subject' => "[Dismissed] " . $this->getHumanReadableAlertType($alertType) . " ({$bucket})",
                        'context' => $logContext,
                        'occurred_at' => now(),
                    ]);
                    \App\Models\MachineHealthHistory::log(
                        $vendId,
                        'machine_health_alert_dismissed',
                        $alertType,
                        $bucket,
                        $lastSent,
                        $logContext
                    );
                }
            }
        }
    }


}
