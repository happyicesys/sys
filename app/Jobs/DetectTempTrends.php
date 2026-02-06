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
            $max24 = $this->getMaxTemp($vend->id, VendTemp::TYPE_EVAPORATOR, 24);
            if ($max24 === null || $max24 > $thresholdRaw) {
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
            } elseif ($max48 !== null && $max48 <= $thresholdRaw) {
                $durationLabel = '> 48 hr';
                $severity = 2;
                $metaMax = $max48;
            }

            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vend->id, 'alert_type' => VendSmartAlert::TYPE_T2_FROZEN],
                [
                    'severity' => $severity,
                    'is_active' => true,
                    'meta_data' => [
                        'max_temp' => $metaMax / $scale,
                        'duration_label' => $durationLabel,
                        'calculated_at' => $now->toIso8601String(),
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
            $this->checkThresholdDuration($vendId, VendTemp::TYPE_EVAPORATOR, -25, '<', [10, 30], VendSmartAlert::TYPE_T2_BELOW_MINUS_25, $scale);
            // T1 & T2 > 0C
            $this->checkDualThresholdDuration($vendId, 0, '>', [30, 60], VendSmartAlert::TYPE_TEMPS_ABOVE_0, $scale);
            // T1 & T2 > -8C
            $this->checkDualThresholdDuration($vendId, -8, '>', [60, 90], VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8, $scale);
            // T1 & T2 did not reach -18C in 8h / 12h
            $this->checkNotReached($vendId, -18, [8, 12], VendSmartAlert::TYPE_NOT_REACH_MINUS_18, $scale);
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

    private function checkThresholdDuration($vendId, $type, $tempC, $operator, $minutes, $alertType, $scale)
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
            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $severity, 'is_active' => true, 'meta_data' => ['val' => $val, 'duration' => $duration]]
            );
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
        }
    }

    private function checkDualThresholdDuration($vendId, $tempC, $operator, $minutes, $alertType, $scale)
    {
        $t1 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)->latest()->first();
        $t2 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)->latest()->first();
        if (!$t1 || !$t2)
            return;

        $v1 = $t1->value / $scale;
        $v2 = $t2->value / $scale;
        $m1 = ($operator === '>') ? ($v1 > $tempC) : ($v1 < $tempC);
        $m2 = ($operator === '>') ? ($v2 > $tempC) : ($v2 < $tempC);

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
            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => ['v1' => $v1, 'v2' => $v2]]
            );
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
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

    private function checkNotReached($vendId, $tempC, $hours, $alertType, $scale)
    {
        // "Did not reach -18" -> Means (MIN(T1) > -18 AND MIN(T2) > -18).
        // If EITHER reached -18 (<= -18), then condition NOT met (Good).

        // Check 12h (Sev 2)
        $minT1_12 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)
            ->where('created_at', '>=', now()->subHours($hours[1]))->min('value');
        $minT2_12 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)
            ->where('created_at', '>=', now()->subHours($hours[1]))->min('value');

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
            $minT1_8 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)
                ->where('created_at', '>=', now()->subHours($hours[0]))->min('value');
            $minT2_8 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)
                ->where('created_at', '>=', now()->subHours($hours[0]))->min('value');

            if ($minT1_8 !== null && $minT2_8 !== null) {
                if ($minT1_8 / $scale > $tempC && $minT2_8 / $scale > $tempC) {
                    $sev = 1;
                }
            }
        }

        if ($sev > 0) {
            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => ['v1' => ($minT1_12 ?? $minT1_8) / $scale]]
            );
        } else {
            VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $alertType)->update(['is_active' => false]);
        }
    }

    private function checkLowestAbove($vendId, $hours, $thresholds, $alertType, $scale)
    {
        $minT1 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_CHAMBER)
            ->where('created_at', '>=', now()->subHours($hours))->min('value');
        $minT2 = VendTemp::where('vend_id', $vendId)->where('type', VendTemp::TYPE_EVAPORATOR)
            ->where('created_at', '>=', now()->subHours($hours))->min('value');

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

        if ($sev > 0) {
            VendSmartAlert::updateOrCreate(
                ['vend_id' => $vendId, 'alert_type' => $alertType],
                ['severity' => $sev, 'is_active' => true, 'meta_data' => ['min_t1' => $v1, 'min_t2' => $v2]]
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
}
