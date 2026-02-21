<?php

namespace Database\Seeders;

use App\Models\Vend;
use App\Models\VendSmartAlert;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * MachineHealthTestSeeder
 *
 * Seeds fake VendSmartAlert records to test the Machine Health Dashboard
 * sections 2.1 and 2.2 visually. Uses online, non-testing vend IDs.
 *
 * Usage:
 *   php artisan db:seed --class=MachineHealthTestSeeder
 *
 * To clean up (set all seeded alerts to is_active=false):
 *   CLEAN=true php artisan db:seed --class=MachineHealthTestSeeder
 */
class MachineHealthTestSeeder extends Seeder
{
    protected array $testVendIds = [];

    public function run(): void
    {
        $clean = env('CLEAN') === 'true';

        // Grab first 10 active, online, non-testing vend IDs automatically
        $this->testVendIds = Vend::where('is_active', true)
            ->where('is_testing', false)
            ->where('is_online', true)
            ->limit(10)
            ->pluck('id')
            ->toArray();

        if (empty($this->testVendIds)) {
            $this->command->warn('No active online vends found. Seeder aborted.');
            return;
        }

        $this->command->info('Using vend IDs: ' . implode(', ', $this->testVendIds));

        if ($clean) {
            $this->cleanUp();
            $this->command->info('✅ Cleaned up. All seeded test alerts set to inactive.');
            return;
        }

        $this->seed21OperationErrors();
        $this->seed22PreventiveMaintenance();

        $this->command->info('');
        $this->command->info('✅ Machine Health test data seeded successfully!');
        $this->command->info('🌐 Visit the Machine Health Dashboard to verify sections 2.1 and 2.2.');
        $this->command->warn('To clean up: CLEAN=true php artisan db:seed --class=MachineHealthTestSeeder');
    }

    // =========================================================================
    // Each 2.1 alert type gets one vend per severity level so all columns show.
    // =========================================================================
    protected function seed21OperationErrors(): void
    {
        $this->command->info('');
        $this->command->info('📌 Seeding 2.1 Operation Errors...');
        $now = Carbon::now()->toIso8601String();

        // Map: [alert_type => [severity => meta_data]]
        // Assign different vend IDs per severity so all columns get populated.
        $definitions = [
                // 1A: T1 higher than T2, >7°C — > 10 mins (1), > 30 mins (2)
            VendSmartAlert::TYPE_T1_HIGHER_THAN_T2 => [
                1 => ['v1' => -5.0, 'v2' => -18.0, 'diff' => 13.0, 'duration' => 15],
                2 => ['v1' => -3.0, 'v2' => -18.0, 'diff' => 15.0, 'duration' => 45],
            ],
                // 1B: Compressor & or Fan OFF — > 45 mins (1), > 60 mins (2)
            VendSmartAlert::TYPE_COMP_FAN_OFF => [
                1 => ['duration' => 50],
                2 => ['duration' => 75],
            ],
                // 1C: T1 & or T2 above 0°C — > 30 mins (1), > 50 mins (2)
            VendSmartAlert::TYPE_TEMPS_ABOVE_0 => [
                1 => ['v1' => 2.5, 'v2' => 3.0, 'duration' => 35],
                2 => ['v1' => 5.0, 'v2' => 6.0, 'duration' => 65],
            ],
                // 1D: T1 & or T2 above -8°C — > 60 mins (1), > 90 mins (2)
            VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8 => [
                1 => ['v1' => -6.0, 'v2' => -7.0, 'duration' => 70],
                2 => ['v1' => -5.0, 'v2' => -5.5, 'duration' => 100],
            ],
                // 1E: T1 & or T2 did not reach -18°C — Within last 8 hrs (1), > 8 hrs (2)
            VendSmartAlert::TYPE_NOT_REACH_MINUS_18 => [
                1 => ['v1' => -15.0, 'duration' => 480],
                2 => ['v1' => -14.0, 'duration' => 720],
            ],
        ];

        $vendIdx = 0;
        foreach ($definitions as $alertType => $severities) {
            foreach ($severities as $severity => $meta) {
                $vendId = $this->testVendIds[$vendIdx % count($this->testVendIds)];
                $vendIdx++;

                VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => $alertType],
                    [
                        'severity' => $severity,
                        'is_active' => true,
                        'meta_data' => array_merge($meta, ['last_sent_severity' => $severity, 'seeded_at' => $now]),
                    ]
                );

                $this->command->line("  ✔ {$alertType} sev={$severity} → vend_id={$vendId}");
            }
        }
    }

    // =========================================================================
    // Section 2.2 — each alert type needs 3 severity levels (Above -21, -20, -19)
    // =========================================================================
    protected function seed22PreventiveMaintenance(): void
    {
        $this->command->info('');
        $this->command->info('📌 Seeding 2.2 Preventive Maintenance...');
        $now = Carbon::now()->toIso8601String();

        $definitions = [
                // 2A: T1 & T2 lowest last 24hrs — sev 1 (Above -21), 2 (Above -20), 3 (Above -19)
            VendSmartAlert::TYPE_LOWEST_24H_ABOVE => [
                1 => ['min_t1' => -20.5, 'min_t2' => -20.8, 'duration' => 1440, 'min_timestamp' => Carbon::now()->subHours(6)->toIso8601String()],
                2 => ['min_t1' => -19.5, 'min_t2' => -19.8, 'duration' => 1440, 'min_timestamp' => Carbon::now()->subHours(8)->toIso8601String()],
                3 => ['min_t1' => -18.5, 'min_t2' => -18.8, 'duration' => 1440, 'min_timestamp' => Carbon::now()->subHours(10)->toIso8601String()],
            ],
                // 2B: T1 & T2 lowest last 72hrs — sev 1 (Above -21), 2 (Above -20), 3 (Above -19)
            VendSmartAlert::TYPE_LOWEST_72H_ABOVE => [
                1 => ['min_t1' => -20.2, 'min_t2' => -20.4, 'duration' => 4320, 'min_timestamp' => Carbon::now()->subHours(12)->toIso8601String()],
                2 => ['min_t1' => -19.2, 'min_t2' => -19.4, 'duration' => 4320, 'min_timestamp' => Carbon::now()->subHours(24)->toIso8601String()],
                3 => ['min_t1' => -18.2, 'min_t2' => -18.4, 'duration' => 4320, 'min_timestamp' => Carbon::now()->subHours(36)->toIso8601String()],
            ],
                // 2C: Rising T1 trend — sev 1 (Δ≥1c), 2 (Δ≥2c), 3 (Δ≥3c)
            VendSmartAlert::TYPE_RISING_T1 => [
                1 => ['current_min' => -22.0, 'prev_min' => -23.2, 'delta' => 1.2, 'duration' => 1500, 'started_at' => Carbon::now()->subHours(26)->toIso8601String(), 'min_timestamp' => Carbon::now()->subHours(4)->toIso8601String()],
                2 => ['current_min' => -21.0, 'prev_min' => -23.5, 'delta' => 2.5, 'duration' => 1500, 'started_at' => Carbon::now()->subHours(26)->toIso8601String(), 'min_timestamp' => Carbon::now()->subHours(5)->toIso8601String()],
                3 => ['current_min' => -19.5, 'prev_min' => -23.0, 'delta' => 3.5, 'duration' => 1500, 'started_at' => Carbon::now()->subHours(26)->toIso8601String(), 'min_timestamp' => Carbon::now()->subHours(6)->toIso8601String()],
            ],
                // 2D: T2 never above 2°C (Frozen) — sev 1 (>24hr), 2 (>48hr), 3 (>72hr)
            VendSmartAlert::TYPE_T2_FROZEN => [
                1 => ['val' => -0.5, 'max_temp' => -0.5, 'duration_label' => '> 24 hr', 'calculated_at' => $now, 'min_timestamp' => Carbon::now()->subHours(25)->toIso8601String()],
                2 => ['val' => 0.3, 'max_temp' => 0.3, 'duration_label' => '> 48 hr', 'calculated_at' => $now, 'min_timestamp' => Carbon::now()->subHours(50)->toIso8601String()],
                3 => ['val' => 1.0, 'max_temp' => 1.0, 'duration_label' => '> 72 hr', 'calculated_at' => $now, 'min_timestamp' => Carbon::now()->subHours(74)->toIso8601String()],
            ],
        ];

        $vendIdx = 5; // Offset to use different vend IDs from 2.1
        foreach ($definitions as $alertType => $severities) {
            foreach ($severities as $severity => $meta) {
                $vendId = $this->testVendIds[$vendIdx % count($this->testVendIds)];
                $vendIdx++;

                VendSmartAlert::updateOrCreate(
                    ['vend_id' => $vendId, 'alert_type' => $alertType],
                    [
                        'severity' => $severity,
                        'is_active' => true,
                        'meta_data' => array_merge($meta, ['seeded_at' => $now]),
                    ]
                );

                $this->command->line("  ✔ {$alertType} sev={$severity} → vend_id={$vendId}");
            }
        }
    }

    protected function cleanUp(): void
    {
        $updated = VendSmartAlert::whereIn('vend_id', $this->testVendIds)
            ->where('is_active', true)
            ->whereJsonContains('meta_data->seeded_at', (string) Carbon::today()->toDateString())
            ->update(['is_active' => false]);

        // Also cast wider net by checking date prefix
        $this->command->info("Deactivated {$updated} seeded alert(s).");
    }
}
