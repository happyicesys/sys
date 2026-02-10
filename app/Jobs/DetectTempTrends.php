<?php

namespace App\Jobs;

use App\Models\VendSmartAlert;
use App\Models\VendTemp;
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
        return $this->targetVendId ?? 'global';
    }

    public ?int $targetVendId;

    /**
     * Create a new job instance.
     */
    public function __construct($vendId = null)
    {
        $this->targetVendId = $vendId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. If triggered by a specific Vend (Real-time), run lightweight stateful check
        if ($this->targetVendId) {
            $this->analyzeStateful($this->targetVendId);
            return;
        }

        // 2. Heavy Analysis (Scheduled Hourly)
        // 2.2: Rising Trends
        $this->analyzeTrend(VendTemp::TYPE_CHAMBER, VendSmartAlert::TYPE_RISING_T1);
        $this->analyzeTrend(VendTemp::TYPE_EVAPORATOR, VendSmartAlert::TYPE_RISING_T2);

        // 2.1: Frozen T2
        $this->analyzeFrozenT2();

        // 2.1: Operation Errors
        $this->analyzeOperationErrors();

        // 2.2: Preventive (High Minima)
        $this->analyzePreventiveMaintenance();
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
        // T2 never above 2C logic
        $targetFrameIds = [5, 6, 7, 10];
        $now = now();
        $scale = 10;
        $thresholdRaw = 2 * $scale;

        $vends = \App\Models\Vend::query()
            ->select('id', 'code', 'vend_prefix_id')
            ->whereIn('menu_frame_id', $targetFrameIds)
            ->whereHas('vendPrefix', function ($q) {
                $q->where('name', 'NOT LIKE', '%UUD%')
                    ->where('name', 'NOT LIKE', '%UDD%');
            })
            ->where('is_active', true)
            ->where('is_testing', false)
            ->get();

        foreach ($vends as $vend) {
            // Check latest T2 for error
            $latestT2 = VendTemp::where('vend_id', $vend->id)
                ->where('type', VendTemp::TYPE_EVAPORATOR)
                ->latest()
                ->first();

            // If current T2 is Error (3276.7), dismiss Frozen Alert
            if ($latestT2 && $latestT2->value == VendTemp::TEMPERATURE_ERROR) {
                VendSmartAlert::where('vend_id', $vend->id)
                    ->where('alert_type', VendSmartAlert::TYPE_T2_FROZEN)
                    ->update(['is_active' => false]);
                continue;
            }

            $max24 = $this->getMaxTemp($vend->id, VendTemp::TYPE_EVAPORATOR, 24);
            $min24 = $this->getMinTemp($vend->id, VendTemp::TYPE_EVAPORATOR, 24);

            // Dismiss if:
            // 1. T2 > 2C (Defrosted/Working)
            // 2. T2 <= -23.5C (Reached very cold temp, so likely fine/not stuck)
            $dismissThresholdRaw = -23.5 * $scale;
            if ($max24 === null || $max24 > $thresholdRaw || ($min24 !== null && $min24 <= $dismissThresholdRaw)) {
                VendSmartAlert::where('vend_id', $vend->id)
                    ->where('alert_type', VendSmartAlert::TYPE_T2_FROZEN)
                    ->update(['is_active' => false]);
                continue;
            }

            $max48 = $this->getMaxTemp($vend->id, VendTemp::TYPE_EVAPORATOR, 48);
            $max72 = $this->getMaxTemp($vend->id, VendTemp::TYPE_EVAPORATOR, 72);

            $durationLabel = '> 24 hr';
            $severity = 1;
            $metaMax = $max24;

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
            } else {
                $windowHr = 24;
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

    private function analyzeOperationErrors(): void
    {
        // 2.1 Operation Error Logic
        $vends = $this->getTargetVends();
        $scale = 10;

        foreach ($vends as $vendId) {
            // T2 < -25C
            $this->checkThresholdDuration($vendId, VendTemp::TYPE_EVAPORATOR, -25, '<', [10, 30], VendSmartAlert::TYPE_T2_BELOW_MINUS_25, $scale, ['> 10 mins', '> 30 mins']);
            // T1 & T2 > 0C
            $this->checkDualThresholdDuration($vendId, 0, '>', [30, 60], VendSmartAlert::TYPE_TEMPS_ABOVE_0, $scale, ['> 30 mins', '> 60 mins']);
            // T1 & T2 > -8C
            $this->checkDualThresholdDuration($vendId, -8, '>', [60, 90], VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8, $scale, ['> 60 mins', '> 90 mins']);
            // T1 & T2 did not reach -18C in 8h / 12h
            $this->checkNotReached($vendId, -18, [8, 12], VendSmartAlert::TYPE_NOT_REACH_MINUS_18, $scale, ['> 8 hours', '> 12 hours']);
        }
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

    private function checkThresholdDuration($vendId, $type, $tempC, $operator, $minutes, $alertType, $scale, $labels = [])
    {
        $latest = VendTemp::where('vend_id', $vendId)->where('type', $type)->latest()->first();
        if (!$latest)
            return;

        $val = $latest->value / $scale;
        $matches = ($operator === '<') ? ($val < $tempC) : ($val > $tempC);

        if (!$matches) {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
            return;
        }

        $maxMin = end($minutes);
        $count = VendTemp::where('vend_id', $vendId)->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes($maxMin + 15))
            ->orderByDesc('created_at')->get();

        $startTime = $latest->created_at;
        foreach ($count as $rec) {
            $v = $rec->value / $scale;
            $m = ($operator === '<') ? ($v < $tempC) : ($v > $tempC);
            if (!$m)
                break;
            $startTime = $rec->created_at;
        }
        $duration = $latest->created_at->diffInMinutes($startTime);

        $severity = 0;
        if ($duration >= $minutes[1])
            $severity = 2;
        elseif ($duration >= $minutes[0])
            $severity = 1;

        if ($severity > 0) {
            // Compute true duration (Last Good)
            $threshRaw = $tempC * $scale;
            // Operator < means Bad is < Thresh. Good is >= Thresh.
            // Operator > means Bad is > Thresh. Good is <= Thresh.
            $op = ($operator === '<') ? '>=' : '<=';

            $lastGood = VendTemp::where('vend_id', $vendId)->where('type', $type)
                ->where('value', $op, $threshRaw)
                ->latest('created_at')
                ->first();

            $trueDuration = $lastGood ? now()->diffInMinutes($lastGood->created_at) : ($minutes[1] + 15);

            // PRESERVE METADATA
            $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->first();
            $metaData = ['val' => $val, 'duration' => $trueDuration, 'min_timestamp' => $latest->created_at->toIso8601String()];
            if ($existing && $existing->is_active && isset($existing->meta_data['last_sent_severity'])) {
                $metaData['last_sent_severity'] = $existing->meta_data['last_sent_severity'];
            }

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $severity, 'is_active' => true, 'meta_data' => $metaData]
            );

            $this->handleEmailAlert($vendId, $alert, $labels);
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false, 'is_email_alert_sent' => false]);
        }
    }

    private function checkDualThresholdDuration($vendId, $tempC, $operator, $minutes, $alertType, $scale, $labels = [])
    {
        $t1 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)->latest()->first();
        $t2 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)->latest()->first();
        if (!$t1 || !$t2)
            return;

        $v1 = $t1->value / $scale;
        $v2 = $t2->value / $scale;
        $m1 = ($operator === '>') ? ($v1 > $tempC) : ($v1 < $tempC);
        $m2 = ($operator === '>') ? ($v2 > $tempC) : ($v2 < $tempC);

        // Explicitly ignore 3276.7 (Sensor Error) for "Above -X" alerts
        // 32767 raw value / 10 = 3276.7
        if ($alertType === VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8 || $alertType === VendSmartAlert::TYPE_TEMPS_ABOVE_0) {
            if (abs($v1 - 3276.7) < 0.01)
                $m1 = false;
            if (abs($v2 - 3276.7) < 0.01)
                $m2 = false;
        }

        if (!($m1 && $m2)) {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
            return;
        }

        $maxMin = end($minutes);
        // Simple approximation: check if all samples in window match? Or just use "now - start".
        // Let's use simple window check: if min(temp) in window > threshold.
        // Actually for > operator, if MIN(temp) in last X mins is > threshold, then ALL are > threshold.
        // Valid for "History" check.
        // If MIN(T1 in last 60m) > 0 AND MIN(T2 in last 60m) > 0 -> Duration met.

        $sev = 0;
        // Check long window first
        if ($this->checkMinMaxInWindow($vendId, $minutes[1], $tempC, $operator, $scale))
            $sev = 2;
        elseif ($this->checkMinMaxInWindow($vendId, $minutes[0], $tempC, $operator, $scale))
            $sev = 1; // Short window

        if ($sev > 0) {
            // Calc true duration (Last Good = Either <= tempC)
            // Operator > means Bad is > Thresh. Good is <= Thresh.
            // Operator < means Bad is < Thresh. Good is >= Thresh.
            // Dual check usually uses > (Warm). So Good is <=.
            $threshRaw = $tempC * $scale;
            $op = ($operator === '>') ? '<=' : '>=';

            $lastGood = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('value', $op, $threshRaw)
                ->latest('created_at')
                ->first();

            $duration = $lastGood ? now()->diffInMinutes($lastGood->created_at) : $minutes[1];

            $latestTimestamp = $t1->created_at->max($t2->created_at);

            // PRESERVE METADATA
            $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->first();
            $metaData = ['v1' => $v1, 'v2' => $v2, 'duration' => $duration, 'min_timestamp' => $latestTimestamp->toIso8601String()];
            if ($existing && $existing->is_active && isset($existing->meta_data['last_sent_severity'])) {
                $metaData['last_sent_severity'] = $existing->meta_data['last_sent_severity'];
            }

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => $metaData]
            );

            $this->handleEmailAlert($vendId, $alert, $labels);
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false, 'is_email_alert_sent' => false]);
        }
    }

    private function checkMinMaxInWindow($vendId, $minDuration, $tempC, $operator, $scale): bool
    {
        // For > operator: Check if MIN temp in last X mins is > tempC.
        // For < operator: Check if MAX temp in last X mins is < tempC.
        $since = now()->subMinutes($minDuration);

        $q1 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)->where('created_at', '>=', $since);
        $q2 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)->where('created_at', '>=', $since);

        if ($operator === '>') {
            $ex1 = $q1->min('value');
            $ex2 = $q2->min('value');
            // If min is null (no data), treat as fail match? Or ignore. Ignore.
            if ($ex1 === null || $ex2 === null)
                return false;
            return ($ex1 / $scale > $tempC) && ($ex2 / $scale > $tempC);
        } else {
            $ex1 = $q1->max('value');
            $ex2 = $q2->max('value');
            if ($ex1 === null || $ex2 === null)
                return false;
            return ($ex1 / $scale < $tempC) && ($ex2 / $scale < $tempC);
        }
    }

    private function checkNotReached($vendId, $tempC, $hours, $alertType, $scale, $labels = [])
    {
        // "Did not reach -18" -> Means (MIN(T1) > -18 AND MIN(T2) > -18).
        // If EITHER reached -18 (<= -18), then condition NOT met (Good).

        // Optimized: Get all min values in one query
        $now = now();
        $time12h = $now->copy()->subHours($hours[1]);
        $time8h = $now->copy()->subHours($hours[0]);

        $temps = VendTemp::where('vend_id', $vendId)
            ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
            ->where('created_at', '>=', $time12h)
            ->selectRaw('
                type,
                MIN(value) as min_12h,
                MIN(CASE WHEN created_at >= ? THEN value END) as min_8h
            ', [$time8h])
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $minT1_12 = $temps->get(VendTemp::TYPE_CHAMBER)?->min_12h;
        $minT2_12 = $temps->get(VendTemp::TYPE_EVAPORATOR)?->min_12h;
        $minT1_8 = $temps->get(VendTemp::TYPE_CHAMBER)?->min_8h;
        $minT2_8 = $temps->get(VendTemp::TYPE_EVAPORATOR)?->min_8h;

        // If either is <= tempC, we are good for 12h.
        // Actually, if it reached in last 12h, does it mean it reached in last 8h? Not necessarily.
        // But severity logic: Sev 2 is worse (12h not reached).

        $sev = 0;
        $failed12 = false;

        if ($minT1_12 !== null && $minT2_12 !== null) {
            // Check if BOTH failed to reach (Both > tempC)
            if ($minT1_12 / $scale > $tempC && $minT2_12 / $scale > $tempC) {
                $sev = 2; // Failed to reach in 12h
                $failed12 = true;
            }
        }

        if (!$failed12) {
            // Check 8h
            if ($minT1_8 !== null && $minT2_8 !== null) {
                if ($minT1_8 / $scale > $tempC && $minT2_8 / $scale > $tempC) {
                    $sev = 1;
                }
            }
        }

        if ($sev > 0) {
            // Find last time EITHER T1 or T2 reached target (<= tempC)
            // tempC is typically -18. So we want <= -18 (good).
            $threshRaw = $tempC * $scale;

            $lastGood = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('value', '<=', $threshRaw)
                ->latest('created_at')
                ->first();

            $duration = 0;
            if ($lastGood) {
                $duration = now()->diffInMinutes($lastGood->created_at);
            } else {
                // Determine max window checked
                // If sev=2, we checked 12h. If sev=1, we checked 8h.
                // Fallback to window duration.
                $windowHours = ($sev == 2) ? $hours[1] : $hours[0];
                $duration = $windowHours * 60;
            }

            // Promote to Severity 2 if duration > first threshold (e.g. 8h)
            // This aligns with "> 8 HOURS" column
            if ($duration > ($hours[0] * 60)) {
                $sev = 2;
            }

            // Find latest timestamp of bad reading (T1 or T2 > tempC)
            $checkWindow = ($sev == 2) ? $time12h : $time8h;
            $threshRaw = $tempC * $scale;

            $latestBad = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('created_at', '>=', $checkWindow)
                ->where('value', '>', $threshRaw)
                ->latest('created_at')
                ->first();

            $minTimestamp = $latestBad ? $latestBad->created_at->toIso8601String() : $now->toIso8601String();

            // PRESERVE METADATA
            $existing = VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->first();
            $metaData = ['v1' => ($minT1_12 ?? $minT1_8) / $scale, 'duration' => $duration, 'min_timestamp' => $minTimestamp];
            if ($existing && $existing->is_active && isset($existing->meta_data['last_sent_severity'])) {
                $metaData['last_sent_severity'] = $existing->meta_data['last_sent_severity'];
            }

            $alert = VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => $metaData]
            );

            $this->handleEmailAlert($vendId, $alert, $labels);
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false, 'is_email_alert_sent' => false]);
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

    private function getMaxTemp(int $vendId, int $type, int $hoursAgo): ?int
    {
        return VendTemp::where('vend_id', $vendId)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours($hoursAgo))
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->max('value');
    }

    private function getMinTemp(int $vendId, int $type, int $hoursAgo): ?int
    {
        return VendTemp::where('vend_id', $vendId)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours($hoursAgo))
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->min('value');
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
                VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_T2_BELOW_MINUS_25],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => ['val' => $t2Val, 'duration' => $diffMinutes]]
                );
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
                VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_0],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => ['v1' => $t1Val, 'v2' => $t2Val]]
                );
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
                VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => ['v1' => $t1Val, 'v2' => $t2Val]]
                );
            }
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
        }
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
                // Determine label if we want to support stateful alerts email (optional, user said 2.1 which is mainly analyzeOperationErrors)
                // If analyzeStateful is also "2.1 Operation Error" but real-time, we should alert too?
                // The user request map "2.1 Operation Error" which corresponds to analyzeOperationErrors() logic primarily.
                // analyzeStateful() is for real-time checking, often parallel to analyzeOperationErrors().
                // To avoid spam, let's keep email logic primarily in analyzeOperationErrors() which is the main "Trend" check.
                // analyzeStateful provides quicker updates but less "historical" context.
                // However, the User Objective mentions: "for temp, only 2.1 needed, 2.2 no need".
                // 2.1 is implemented in analyzeOperationErrors().
                // analyzeStateful() also implements the same logic but statefully.
                // Let's assume strict 2.1 in analyzeOperationErrors() is sufficient for "Email Alerts" as it covers the "Trends".

                VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => VendSmartAlert::TYPE_NOT_REACH_MINUS_18],
                    ['severity' => $severity, 'is_active' => true, 'meta_data' => ['v1' => $t1Val]]
                );
            }
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', VendSmartAlert::TYPE_NOT_REACH_MINUS_18)->update(['is_active' => false]);
        }
    }

    private function handleEmailAlert($vendId, $alert, $labels)
    {
        // $alert->severity maps to index in $labels (Severity 1 = labels[0], Sev 2 = labels[1])
        // Indices are 0-based, severity is 1,2,3...
        $labelIndex = $alert->severity - 1;
        $label = $labels[$labelIndex] ?? 'Unknown';

        // Check if email already sent for THIS severity level
        $meta = $alert->meta_data;
        $lastSentSeverity = $meta['last_sent_severity'] ?? 0;

        if ($alert->severity > $lastSentSeverity) {
             // Send Email
             $vend = \App\Models\Vend::find($vendId);
             if ($vend) {
                 $alertService = app(\App\Services\AlertEmailService::class);
                 $alertService->sendVendOperationErrorNotificationMail($vend, $alert->alert_type, $label);

                 // Update state
                 $meta['last_sent_severity'] = $alert->severity;
                 $alert->meta_data = $meta;
                 $alert->is_email_alert_sent = true;
                 $alert->email_alert_sent_at = now();
                 $alert->save();
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
