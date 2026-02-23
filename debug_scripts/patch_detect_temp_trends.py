import re

with open('app/Jobs/DetectTempTrends.php', 'r') as f:
    content = f.read()

# 1. Update handle()
handle_orig = """    public function handle(): void
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

            // 3: No Transactions
            $this->checkNoTransactions($this->targetVendId);

            // 5: Stockouts
            $this->checkStockouts($this->targetVendId);

            // 2.1: Frozen T2
            $this->analyzeFrozenT2();

            // 2.1 Operation Errors: REMOVED.
            // We rely on analyzeStateful() which runs in both modes (Realtime & Hourly)
            // and persists state to DB, avoiding redundant heavy historical queries.

            // 2.2: Preventive (High Minima)
            $this->analyzePreventiveMaintenance();
        }
    }"""

handle_new = """    public function handle(): void
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
    }"""
content = content.replace(handle_orig, handle_new)

# 2. Delete getTargetVends()
content = re.sub(r'    private function getTargetVends\(\)\n    \{[\s\S]*?    \}\n\n', '', content)

# 3. Update analyzeFrozenT2()
frozen_orig = """    private function analyzeFrozenT2(): void
    {
        // Optimization: Use target vend if available
        $vends = $this->targetVendId
            ? \App\Models\Vend::where('id', $this->targetVendId)->whereIn('menu_frame_id', [5, 6, 7, 10])->get()
            : $this->getFrozenT2Candidates();

        $scale = 10;
        $thresholdRaw = 2 * $scale;
        $now = now();
        $calc72h = $now->copy()->subHours(72);

        foreach ($vends as $vend) {"""

frozen_new = """    private function analyzeFrozenT2(\App\Models\Vend $vend): void
    {
        if (!in_array($vend->menu_frame_id, [5, 6, 7, 10])) {
            return;
        }

        $scale = 10;
        $thresholdRaw = 2 * $scale;
        $now = now();
        $calc72h = $now->copy()->subHours(72);"""

content = content.replace(frozen_orig, frozen_new)

# replace } of foreach in analyzeFrozenT2
content = content.replace("""            );
            $this->logDashboardAlert($vend->id, $alert, ['> 24 hr', '> 48 hr', '> 72 hr']);
        }
    }""", """            );
        $this->logDashboardAlert($vend->id, $alert, ['> 24 hr', '> 48 hr', '> 72 hr']);
    }""")

# fix continue -> return in analyzeFrozenT2
content = re.sub(r'(private function analyzeFrozenT2[\s\S]*?\$this->logDashboardAlert\(\$vend->id, \$alert, \[\'> 24 hr\', \'> 48 hr\', \'> 72 hr\'\]\);\n    \})', lambda m: m.group(1).replace('                 continue;\n', '                return;\n').replace('                continue;\n', '                return;\n'), content)


# 4. Delete getFrozenT2Candidates()
content = re.sub(r'    private function getFrozenT2Candidates\(\)\n    \{[\s\S]*?    \}\n\n', '', content)

# 5. Update clearSmartAlert()
content = content.replace("""    private function clearSmartAlert($vendId, $type)
    {
        VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $type)->update(['is_active' => false]);
    }""", """    private function clearSmartAlert($vendId, $type)
    {
        VendSmartAlert::where('vend_id', $vendId)->where('alert_type', $type)->where('is_active', true)->update(['is_active' => false]);
    }""")

# 6. Update analyzePreventiveMaintenance()
prev_orig = """    private function analyzePreventiveMaintenance(): void
    {
        // 2.2 Preventive Logic
        $vends = $this->getTargetVends();
        $scale = 10;

        foreach ($vends as $vendId) {
            $this->checkLowestAbove($vendId, 24, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_24H_ABOVE, $scale);
            $this->checkLowestAbove($vendId, 72, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_72H_ABOVE, $scale);
        }
    }"""
prev_new = """    private function analyzePreventiveMaintenance(\App\Models\Vend $vend): void
    {
        $scale = 10;
        $this->checkLowestAbove($vend->id, 24, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_24H_ABOVE, $scale);
        $this->checkLowestAbove($vend->id, 72, [-21, -20, -19], VendSmartAlert::TYPE_LOWEST_72H_ABOVE, $scale);
    }"""
content = content.replace(prev_orig, prev_new)

# 7. Add 14 day limit to checkLowestAbove fallback query so it never full scans
fallback_orig = """            $lastGood = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('value', '<=', $threshRaw)
                ->latest('created_at')
                ->first();"""
fallback_new = """            $lastGood = VendTemp::where('vend_id', $vendId)
                ->whereIn('type', [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR])
                ->where('value', '<=', $threshRaw)
                ->where('created_at', '>=', now()->subDays(14))
                ->latest('created_at')
                ->first();"""
content = content.replace(fallback_orig, fallback_new)


# 8. Update analyzeTrend()
trend_orig_regex = r'    private function analyzeTrend\(int \$tempType, string \$alertType\): void\n    \{[\s\S]*?            \$this->resolveStatefulAlert\(\$vendId, \$alertType, \[\'\Δ \≥ 1c\', \'\Δ \≥ 2c\', \'\Δ \≥ 3c\'\]\);\n        \}\n    \}'

trend_new = """    private function analyzeTrend(\App\Models\Vend $vend, int $tempType, string $alertType): void
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
            if ($deltaC >= 3.0) $severity = 3;
            elseif ($deltaC >= 2.0) $severity = 2;

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
    }"""
content = re.sub(trend_orig_regex, trend_new.replace('\\', '\\\\'), content)


# 9. Update analyzeStateful()
stateful_orig1 = """    private function analyzeStateful(int $vendId): void
    {
        $vend = \App\Models\Vend::find($vendId);
        if (!$vend || !$vend->is_active || $vend->is_testing) {
            return;
        }"""
stateful_new1 = """    private function analyzeStateful(\App\Models\Vend $vend): void
    {
        $vendId = $vend->id;"""
content = content.replace(stateful_orig1, stateful_new1)

stateful_orig2 = """        // Save State
        if ($state !== $newState) {
            $vend->temp_monitoring_state = $newState;
            $vend->save();
        }"""
stateful_new2 = """        // Save State
        if ($state !== $newState) {
            $vend->temp_monitoring_state = $newState;
        }"""
content = content.replace(stateful_orig2, stateful_new2)


# 10. Update checkConnectivity()
connectivity_orig = """    private function checkConnectivity($vendId): void
    {
        $vend = \App\Models\Vend::find($vendId);
        if (!$vend) {
            \Log::info("Vend $vendId not found in checkConnectivity");
            return;
        }"""
connectivity_new = """    private function checkConnectivity(\App\Models\Vend $vend): void
    {"""
content = content.replace(connectivity_orig, connectivity_new)

# Remove ->save() in checkConnectivity()
content = re.sub(r'(\$vend->temp_monitoring_state = \$state;\n                )\$vend->save\(\);\n', r'\1', content)


# 11. Update checkNoTransactions()
notxn_orig = """    private function checkNoTransactions($vendId): void
    {
        $vends = $vendId ? [\App\Models\Vend::find($vendId)] : \App\Models\Vend::where('is_active', true)->where('is_testing', false)->get();
        if (empty($vends))
            return;
        $now = now();

        foreach ($vends as $vend) {
            if (!$vend)
                continue;

            $state = $vend->temp_monitoring_state ?? [];"""

notxn_new = """    private function checkNoTransactions(\App\Models\Vend $vend): void
    {
        $now = now();
        $state = $vend->temp_monitoring_state ?? [];"""

content = content.replace(notxn_orig, notxn_new)

# Indentation outdent and remove save() in checkNoTransactions
notxn_end_orig = """            if (json_encode($lastNoTxnBuckets) !== json_encode($newNoTxnBuckets)) {
                $state['no_txn_buckets'] = $newNoTxnBuckets;
                $vend->temp_monitoring_state = $state;
                $vend->save();
            }
        }
    }"""

notxn_end_new = """        if (json_encode($lastNoTxnBuckets) !== json_encode($newNoTxnBuckets)) {
            $state['no_txn_buckets'] = $newNoTxnBuckets;
            $vend->temp_monitoring_state = $state;
        }
    }"""
content = content.replace(notxn_end_orig, notxn_end_new)


# Regex fix for indentation inside checkNoTransactions (since I removed loop, just replace 4 spaces with empty for those lines)
# It's tedious to fix all indentation, PHP handles it fine anyway.
# We will just let indentation be, or manually fix the exact block. I'll ignore indentation formatting for now to ensure safe replacement.


# 12. Update checkStockouts()
stockouts_orig = """    private function checkStockouts($vendId): void
    {
        $vends = $vendId ? [\App\Models\Vend::find($vendId)] : \App\Models\Vend::where('is_active', true)->where('is_testing', false)->get();
        if (empty($vends))
            return;
        $now = now();
        $targetHours = 72;

        foreach ($vends as $vend) {
            if (!$vend)
                continue;

            $state = $vend->temp_monitoring_state ?? [];"""

stockouts_new = """    private function checkStockouts(\App\Models\Vend $vend): void
    {
        $now = now();
        $targetHours = 72;

        $state = $vend->temp_monitoring_state ?? [];"""
content = content.replace(stockouts_orig, stockouts_new)


stockouts_end_orig = """            if (json_encode($lastStockoutBuckets) !== json_encode($newStockoutBuckets)) {
                $state['stockout_buckets'] = $newStockoutBuckets;
                $vend->temp_monitoring_state = $state;
                $vend->save();
            }
        }
    }"""

stockouts_end_new = """        if (json_encode($lastStockoutBuckets) !== json_encode($newStockoutBuckets)) {
            $state['stockout_buckets'] = $newStockoutBuckets;
            $vend->temp_monitoring_state = $state;
        }
    }"""
content = content.replace(stockouts_end_orig, stockouts_end_new)

with open('app/Jobs/DetectTempTrends.php', 'w') as f:
    f.write(content)

print("Patching complete!")
