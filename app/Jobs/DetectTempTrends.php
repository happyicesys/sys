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
        if (!$this->targetVendId) {
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

        $vend = \App\Models\Vend::find($this->targetVendId);
        if (!$vend || !$vend->is_active || $vend->is_testing) {
            return;
        }

        $this->analyzeStateful($vend);
        $this->checkConnectivity($vend);

        if ($this->isFullScan) {
            $this->analyzeTrend($vend, VendTemp::TYPE_CHAMBER, VendSmartAlert::TYPE_RISING_T1);
            $this->analyzeTrend($vend, VendTemp::TYPE_EVAPORATOR, VendSmartAlert::TYPE_RISING_T2);
            $this->checkNoTransactions($vend);
            $this->checkStockouts($vend);
            $this->analyzeFrozenT2($vend);
            $this->analyzePreventiveMaintenance($vend);
        }

        if ($vend->isDirty()) {
            $vend->save();
        }
    }

    private function analyzeFrozenT2(\App\Models\Vend $vend): void
    {
        if (!in_array($vend->menu_frame_id, [5, 6, 7, 10])) {
            return;
        }

        $scale = 10;
        $thresholdRaw = 2 * $scale;
        $now = now();
        $calc72h = $now->copy()->subHours(72);
        // 1. Check latest status first (Fail fast)
        $latestT2 = VendTemp::where('vend_id', $vend->id)
            ->where('type', VendTemp::TYPE_EVAPORATOR)
            ->latest()
            ->first();

        // Dismiss if Error Code or Missing
        if (!$latestT2 || $latestT2->value == VendTemp::TEMPERATURE_ERROR) {
            $this->clearSmartAlert($vend->id, VendSmartAlert::TYPE_T2_FROZEN);
            return;
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
            return;

        $max24 = $stats->max_24;
        $min24 = $stats->min_24;

        // Dismiss if:
        // 1. T2 > 2C (Defrosted/Working) in last 24h
        // 2. T2 <= -23.5C (Reached very cold temp) in last 24h
        $dismissThresholdRaw = -23.5 * $scale;
        if ($max24 === null || $max24 > $thresholdRaw || ($min24 !== null && $min24 <= $dismissThresholdRaw)) {
            $this->resolveStatefulAlert($vend->id, VendSmartAlert::TYPE_T2_FROZEN, ['> 24 hr', '> 48 hr', '> 72 hr']);
            return;
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

        $alert = VendSmartAlert::updateOrCreate(
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
        $this->logDashboardAlert($vend->id, $alert, ['> 24 hr', '> 48 hr', '> 72 hr']);
    }

    private function clearSmartAlert($vendId, $type)
    {
        VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $type)->where('is_active', true)->update(['is_active' => false]);
    }

    private function analyzePreventiveMaintenance(\App\Models\Vend $vend): void
    {
        $scale = 10;
        $this->checkLowestAbove($vend->id, 24, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_24H_ABOVE, $scale);
        $this->checkLowestAbove($vend->id, 72, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_72H_ABOVE, $scale);
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
                ->where('created_at', '>=', now()->subDays(14))
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

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => ['min_t1' => $v1, 'min_t2' => $v2, 'duration' => $duration, 'min_timestamp' => $minTimestamp]]
            );
            $this->logDashboardAlert($vendId, $alert, ['Above -21c', 'Above -20c', 'Above -19c']);
        } else {
            $this->resolveStatefulAlert($vendId, $alertType, ['Above -21c', 'Above -20c', 'Above -19c']);
        }
    }

    private function analyzeTrend(\App\Models\Vend $vend, int $tempType, string $alertType): void
    {
        $now = now();
        $windowCurrentStart = $now->copy()->subHours(24);
        $windowPrevStart = $now->copy()->subHours(48);

        $curr = VendTemp::query()
            ->where('vend_id', $vend->id)
            ->where('type', $tempType)
            ->whereBetween('created_at', [$windowCurrentStart, $now])
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->min('value');

        $prev = VendTemp::query()
            ->where('vend_id', $vend->id)
            ->where('type', $tempType)
            ->whereBetween('created_at', [$windowPrevStart, $windowCurrentStart])
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->min('value');

        if (is_null($curr) || is_null($prev) || abs($curr - 32767) < 1 || abs($prev - 32767) < 1) {
            $this->resolveStatefulAlert($vend->id, $alertType, ['Δ ≥ 1c', 'Δ ≥ 2c', 'Δ ≥ 3c']);
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

            $existingAlert = VendSmartAlert::where('vend_id', $vend->id)->where('alert_type', $alertType)->first();
            $startedAt = $now->toIso8601String();
            if ($existingAlert && $existingAlert->is_active && isset($existingAlert->meta_data['started_at'])) {
                $startedAt = $existingAlert->meta_data['started_at'];
            }

            $elapsedMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($startedAt));
            $duration = (24 * 60) + $elapsedMinutes;

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vend->id, 'alert_type' => $alertType],
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
                        'min_timestamp' => $this->findMinTimestamp($vend->id, $tempType, $windowCurrentStart, $now, $curr),
                        'prev_min_timestamp' => $this->findMinTimestamp($vend->id, $tempType, $windowPrevStart, $windowCurrentStart, $prev),
                    ],
                ]
            );
            $this->logDashboardAlert($vend->id, $alert, ['Δ ≥ 1c', 'Δ ≥ 2c', 'Δ ≥ 3c']);
        } else {
            $this->resolveStatefulAlert($vend->id, $alertType, ['Δ ≥ 1c', 'Δ ≥ 2c', 'Δ ≥ 3c']);
        }
    }

    private function analyzeStateful(\App\Models\Vend $vend): void
    {
        $vendId = $vend->id;

        // Get latest temps (O(1) with index)
        // Optimized: Single query to get both latest temps
        // NOTE: We do NOT filter out TEMPERATURE_ERROR here.
        // If the latest reading is Error, we want to know it so we can CLEAR the "Frozen" alert
        // (treating it as 'not frozen') rather than falling back to an older 'frozen' reading.
        $latestTemps = VendTemp::where('vend_id', $vendId)
            ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
            ->orderBy('created_at', 'desc')
            ->take(10) // Small buffer to ensure we get both types
            ->get();

        $t1 = $latestTemps->where('type', VendTemp::TYPE_CHAMBER)->first();
        $t2 = $latestTemps->where('type', VendTemp::TYPE_EVAPORATOR)->first();

        // If no recent data skip.
        if (!$t1 || !$t2)
            return;

        $t1Val = $t1->value / 10;
        $t2Val = $t2->value / 10;
        $now = now();

        $state = $vend->temp_monitoring_state ?? [];
        $newState = $state;

        // --- Logic: 1A) T1 higher than T2, >7°C ---
        if (($t1Val - $t2Val) > 7 && $t1->value != VendTemp::TEMPERATURE_ERROR && $t2->value != VendTemp::TEMPERATURE_ERROR) {
            if (!isset($state['t1_higher_t2_start'])) {
                $newState['t1_higher_t2_start'] = $t1->created_at->max($t2->created_at)->toIso8601String();
            }
        } else {
            unset($newState['t1_higher_t2_start']);
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_T1_HIGHER_THAN_T2, ['> 10 mins', '> 30 mins']);
        }

        // --- Logic: 1B) Compressor & or Fan OFF ---
        // Based on fan's value
        $latestFan = \App\Models\VendFan::where('vend_id', $vendId)->where('type', \App\Models\VendFan::TYPE_MAIN)->orderBy('created_at', 'desc')->first();
        $fanIsOff = false;
        if ($latestFan && $now->diffInMinutes($latestFan->created_at) > 40) {
            $fanIsOff = true;
        }

        if ($fanIsOff && $vend->is_online) {
            if (!isset($state['comp_fan_off_start'])) {
                $startOff = $latestFan ? $latestFan->created_at : $now;
                $newState['comp_fan_off_start'] = $startOff->toIso8601String();
            }
        } else {
            unset($newState['comp_fan_off_start']);
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_COMP_FAN_OFF, ['> 45 mins', '> 60 mins']);
        }

        // --- Logic: T1 & T2 > 0 (Warm) ---
        // Ensure Error (3276.7) or unreasonably high temps do not trigger warm alerts
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

        // Save State
        if ($state !== $newState) {
            $vend->temp_monitoring_state = $newState;
        }

        // --- Evaluate Alerts with Priority Suppression ---
        // 1A. T1 higher than T2, >7°C (Independent)
        if (isset($newState['t1_higher_t2_start'])) {
            $diffMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($newState['t1_higher_t2_start']), true);
            $severity = 0;
            if ($diffMinutes >= 30)
                $severity = 2;
            elseif ($diffMinutes >= 10)
                $severity = 1;

            if ($severity > 0) {
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_T1_HIGHER_THAN_T2)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;
                $meta['v2'] = $t2Val;
                $meta['diff'] = round($t1Val - $t2Val, 2);
                $meta['duration'] = $diffMinutes;

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_T1_HIGHER_THAN_T2],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 30 : 10;
                $effectiveStart = \Carbon\Carbon::parse($newState['t1_higher_t2_start']);
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(10);
                $this->handleEmailAlert($vend, $alert, ['> 10 mins', '> 30 mins'], $occurredAt, $firstTriggerAt);
            }
        }

        // 1B. Compressor & or Fan OFF (Independent)
        if (isset($newState['comp_fan_off_start']) && $vend->is_online) {
            $diffMinutes = $now->diffInMinutes(\Carbon\Carbon::parse($newState['comp_fan_off_start']), true);
            $severity = 0;
            if ($diffMinutes >= 60)
                $severity = 2;
            elseif ($diffMinutes >= 45)
                $severity = 1;

            if ($severity > 0) {
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_COMP_FAN_OFF)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['duration'] = $diffMinutes;

                $alert = VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_COMP_FAN_OFF],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => $meta]
                );
                $thresholdMinutes = $severity === 2 ? 60 : 45;
                $effectiveStart = \Carbon\Carbon::parse($newState['comp_fan_off_start']);
                $occurredAt = $effectiveStart->copy()->addMinutes($thresholdMinutes);
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(45);
                $this->handleEmailAlert($vend, $alert, ['> 45 mins', '> 60 mins'], $occurredAt, $firstTriggerAt);
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
            if ($diffMinutes >= 60)
                $severity = 2;
            elseif ($diffMinutes >= 30)
                $severity = 1;

            if ($severity > 0) {
                $isCaseBActive = true; // Flag active to suppress lower alerts
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
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(30);
                $this->handleEmailAlert($vend, $alert, ['> 30 mins', '> 60 mins'], $occurredAt, $firstTriggerAt);
            }
        } else {
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_TEMPS_ABOVE_0, ['> 30 mins', '> 60 mins']);
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
                $firstTriggerAt = $effectiveStart->copy()->addMinutes(60);
                $this->handleEmailAlert($vend, $alert, ['> 60 mins', '> 90 mins'], $occurredAt, $firstTriggerAt);
            }
        } else {
            // Must clear if Case B is active OR if condition not met
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8, ['> 60 mins', '> 90 mins']);
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
                $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_NOT_REACH_MINUS_18)->first();
                $meta = $existing ? ($existing->meta_data ?? []) : [];
                $meta['v1'] = $t1Val;

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
            $this->resolveStatefulAlert($vendId, VendSmartAlert::TYPE_NOT_REACH_MINUS_18, ['Within last 8 hours', '> 8 hours']);
        }
    }



    private function handleEmailAlert($vend, $alert, $labels, $occurredAt = null, $originalTriggerAt = null)
    {
        // $alert->severity maps to index in $labels (Severity 1 = labels[0], Sev 2 = labels[1])
        // Indices are 0-based, severity is 1,2,3...
        $labelIndex = $alert->severity - 1;
        $label = $labels[$labelIndex] ?? 'Unknown';

        // Check if email/log already sent for THIS severity level
        $meta = $alert->meta_data;
        $lastSentSeverity = $meta['last_sent_severity'] ?? 0;

        if ($alert->severity > $lastSentSeverity) {
            // $vend is passed directly, no need to find()
            if ($vend) {
                // Delete previous lower-severity logs (if escalating)
                // Only keep the log for the current highest severity
                if ($lastSentSeverity > 0) {
                    for ($s = 1; $s < $alert->severity; $s++) {
                        $oldLabelIndex = $s - 1;
                        if (isset($labels[$oldLabelIndex])) {
                            $oldLabel = $labels[$oldLabelIndex];
                            VendLog::where('vend_id', $vend->id)
                                ->where('event', 'machine_health_alert')
                                ->where('context->bucket', $oldLabel)
                                ->where('context->alert_type', $alert->alert_type)
                                ->delete();
                        }
                    }
                }

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
                        'triggered_at' => $originalTriggerAt ? $originalTriggerAt->toIso8601String() : ($occurredAt ?? now())->toIso8601String(),
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

    private function logDashboardAlert(int $vendId, $alert, array $labels, $occurredAt = null, $originalTriggerAt = null): void
    {
        $labelIndex = $alert->severity - 1;
        $label = $labels[$labelIndex] ?? 'Unknown';

        $meta = $alert->meta_data;
        $lastSentSeverity = $meta['last_sent_severity'] ?? 0;

        if ($alert->severity > $lastSentSeverity) {
            if ($lastSentSeverity > 0) {
                for ($s = 1; $s < $alert->severity; $s++) {
                    $oldLabelIndex = $s - 1;
                    if (isset($labels[$oldLabelIndex])) {
                        $oldLabel = $labels[$oldLabelIndex];
                        VendLog::where('vend_id', $vendId)
                            ->where('event', 'machine_health_alert')
                            ->where('context->bucket', $oldLabel)
                            ->where('context->alert_type', $alert->alert_type)
                            ->delete();
                    }
                }
            }

            VendLog::create([
                'vend_id' => $vendId,
                'event' => 'machine_health_alert',
                'subject' => $this->getHumanReadableAlertType($alert->alert_type) . " ({$label})",
                'context' => [
                    'bucket' => $label,
                    'alert_type' => $alert->alert_type,
                    'severity' => $alert->severity,
                    'triggered_at' => $originalTriggerAt ? $originalTriggerAt->toIso8601String() : ($occurredAt ?? now())->toIso8601String(),
                    'meta' => $meta,
                ],
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $meta['last_sent_severity'] = $alert->severity;
            $alert->meta_data = $meta;
            $alert->save();
        }
    }

    private function getHumanReadableAlertType($type)
    {
        return match ($type) {
            VendSmartAlert::TYPE_T1_HIGHER_THAN_T2 => 'T1 higher than T2, >7°C',
            VendSmartAlert::TYPE_COMP_FAN_OFF => 'Compressor & or Fan OFF',
            VendSmartAlert::TYPE_TEMPS_ABOVE_0 => 'T1 & or T2 above 0°C',
            VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8 => 'T1 & or T2 above -8°C',
            VendSmartAlert::TYPE_NOT_REACH_MINUS_18 => 'T1 & or T2 did not reach -18°C',
            VendSmartAlert::TYPE_LOWEST_24H_ABOVE => 'T1 & T2 lowest (last 24hrs)',
            VendSmartAlert::TYPE_LOWEST_72H_ABOVE => 'T1 & T2 lowest (last 72hrs)',
            VendSmartAlert::TYPE_RISING_T1 => 'Rising lowest T1',
            VendSmartAlert::TYPE_RISING_T2 => 'Rising lowest T2',
            VendSmartAlert::TYPE_T2_FROZEN => 'T2, never above 2°C',
            default => $type,
        };
    }

    private function checkConnectivity(\App\Models\Vend $vend): void
    {

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

        // Check if back online
        if (!$bucket && $hoursOffline < 0.25) {
            $state = $vend->temp_monitoring_state ?? [];
            $lastBucket = $state['connectivity_last_bucket'] ?? null;

            if ($lastBucket) {
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert_dismissed',
                    'subject' => "Back Online (was {$lastBucket})",
                    'context' => [
                        'bucket' => $lastBucket,
                        'type' => 'connectivity',
                    ],
                    'occurred_at' => now(),
                ]);

                unset($state['connectivity_last_bucket']);
                $vend->temp_monitoring_state = $state;
            }
            return;
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
            // If escalating from a previous bucket, delete the old alert log
            // so only the highest escalation level keeps a log
            if ($lastBucket) {
                VendLog::where('vend_id', $vend->id)
                    ->where('event', 'machine_health_alert')
                    ->where('context->bucket', $lastBucket)
                    ->where('context->type', 'connectivity')
                    ->delete();
            }

            // Log the new bucket
            VendLog::create([
                'vend_id' => $vend->id,
                'event' => 'machine_health_alert',
                'subject' => "Offline ({$bucket})",
                'context' => [
                    'bucket' => $bucket,
                    'type' => 'connectivity',
                    'hours_offline' => $hoursOffline,
                    'triggered_at' => $lastContact ? $lastContact->copy()->addMinutes(15)->toIso8601String() : $occurredAt->toIso8601String(),
                ],
                'occurred_at' => $occurredAt,
            ]);

            // Update state
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
        $paramJson = $vend->parameter_json;
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
        $paJson = $vend->acb_vmc_pa_json;
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
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert',
                    'subject' => "No {$data['label']} (>= {$data['hours']}hr)",
                    'context' => [
                        'bucket' => ">= {$data['hours']}hr",
                        'type' => "no_txn_{$key}",
                        'hours_offline' => $data['diff'],
                    ],
                    'occurred_at' => $now,
                ]);
            }
        }

        foreach ($lastNoTxnBuckets as $key => $data) {
            if (!isset($newNoTxnBuckets[$key])) {
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert_dismissed',
                    'subject' => "Transaction Received ({$data['label']})",
                    'context' => [
                        'bucket' => ">= {$data['hours']}hr",
                        'type' => "no_txn_{$key}",
                    ],
                    'occurred_at' => $now,
                ]);
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

        $state = $vend->temp_monitoring_state ?? [];
        $lastStockoutBuckets = $state['stockout_buckets'] ?? [];
        $newStockoutBuckets = [];

        // Fetch latest event per channel for this vend to find active stockouts
        $latestEvents = \App\Models\VendChannelStockEvent::where('vend_id', $vend->id)
            ->orderBy('occurred_at', 'desc')
            ->get()
            ->unique('vend_channel_id');

        foreach ($latestEvents as $event) {
            if ($event->event_type === \App\Models\VendChannelStockEvent::TYPE_SOLD_OUT) {
                $durationHours = $event->occurred_at->diffInMinutes($now) / 60;
                if ($durationHours >= $targetHours) {
                    $channelId = $event->vend_channel_id;
                    $channel = \App\Models\VendChannel::find($channelId);
                    $code = $channel ? $channel->code : "Unknown";

                    $newStockoutBuckets[$channelId] = [
                        'channel_code' => $code,
                        'hours' => $targetHours,
                        'diff' => $durationHours,
                    ];
                }
            }
        }

        foreach ($newStockoutBuckets as $channelId => $data) {
            if (!isset($lastStockoutBuckets[$channelId])) {
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert',
                    'subject' => "Stockout Channel {$data['channel_code']} (>= {$data['hours']}hr)",
                    'context' => [
                        'bucket' => ">= {$data['hours']}hr",
                        'type' => "stockout_channel_{$channelId}",
                        'hours_offline' => $data['diff'],
                    ],
                    'occurred_at' => $now,
                ]);
            }
        }

        foreach ($lastStockoutBuckets as $channelId => $data) {
            if (!isset($newStockoutBuckets[$channelId])) {
                VendLog::create([
                    'vend_id' => $vend->id,
                    'event' => 'machine_health_alert_dismissed',
                    'subject' => "Restocked Channel {$data['channel_code']}",
                    'context' => [
                        'bucket' => ">= {$data['hours']}hr",
                        'type' => "stockout_channel_{$channelId}",
                    ],
                    'occurred_at' => $now,
                ]);
            }
        }

        if (json_encode($lastStockoutBuckets) !== json_encode($newStockoutBuckets)) {
            $state['stockout_buckets'] = $newStockoutBuckets;
            $vend->temp_monitoring_state = $state;
        }
    }

    private function resolveStatefulAlert(int $vendId, string $alertType, array $labels): void
    {
        $alert = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->first();

        if ($alert && $alert->is_active) {
            $alert->update(['is_active' => false]);

            // Only log dismissal for the highest severity that was actually sent
            // (lower severities were deleted during escalation)
            $lastSent = $alert->meta_data['last_sent_severity'] ?? $alert->severity;

            // Cap lastSent just in case
            if ($lastSent > count($labels))
                $lastSent = count($labels);

            // Only create ONE dismissal log for the last sent severity
            if ($lastSent > 0) {
                $labelIndex = $lastSent - 1;
                if (isset($labels[$labelIndex])) {
                    $bucket = $labels[$labelIndex];

                    VendLog::create([
                        'vend_id' => $vendId,
                        'event' => 'machine_health_alert_dismissed',
                        'subject' => "Dismissed ({$bucket})",
                        'context' => [
                            'bucket' => $bucket,
                            'alert_type' => $alertType,
                            'severity' => $lastSent,
                        ],
                        'occurred_at' => now(),
                    ]);
                }
            }
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
