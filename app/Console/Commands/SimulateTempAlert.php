<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Models\VendTemp;
use App\Models\VendSmartAlert;
use App\Jobs\DetectTempTrends;
use Carbon\Carbon;

class SimulateTempAlert extends Command
{
    protected $signature = 'simulate:temp-alert {action : init|seed|trigger|check} {--vend_valid=0}';
    protected $description = 'Simulate temperature alert scenarios for testing';

    public function handle()
    {
        $action = $this->argument('action');

        // Use real Vend 4752
        $code = '4752';
        $vend = Vend::where('code', $code)->first();

        if (!$vend) {
            $this->error("Vend $code not found! Please check the code.");
            return;
        }

        $this->info("Using Vend: {$vend->code} (ID: {$vend->id})");

        switch ($action) {
            case 'init':
                $this->init($vend);
                break;
            case 'seed':
                $this->seed($vend);
                break;
            case 'trigger':
                $this->trigger($vend);
                break;
            case 'check':
                $this->check($vend);
                break;
            default:
                $this->error("Unknown action: $action");
        }
    }

    private function init($vend)
    {
        // Clear State
        $vend->temp_monitoring_state = null;
        $vend->save();
        $this->info("Cleared Temp Monitoring State.");

        // Clear Old Alerts
        VendSmartAlert::where('vend_id', $vend->id)->delete();
        $this->info("Deleted old Smart Alerts.");

        // Clear Recent Temps (last 24h)
        VendTemp::where('vend_id', $vend->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->delete();
        $this->info("Deleted recent Temp records.");

        $this->info("Initialization Complete. Run 'seed' next to populate history.");
    }

    private function seed($vend)
    {
        $now = now();
        $start = $now->copy()->subHours(2);

        $data = [];
        // Seed "Normal" data (-20C) from -15h to -14h
        // Interval: 1 minute
        $current = $now->copy()->subHours(15);
        $until = $now->copy()->subHours(14);

        $count = 0;
        while ($current < $until) {
            // ...
            $data[] = [
                'vend_id' => $vend->id,
                'type' => VendTemp::TYPE_EVAPORATOR,
                'value' => -200,
                'created_at' => $current->toDateTimeString(),
                'updated_at' => $current->toDateTimeString(),
            ];
             $data[] = [
                'vend_id' => $vend->id,
                'type' => VendTemp::TYPE_CHAMBER,
                'value' => -200,
                'created_at' => $current->toDateTimeString(),
                'updated_at' => $current->toDateTimeString(),
            ];
            $current->addMinute();
            $count++;
        }

        VendTemp::insert($data);
        $this->info("Seeded $count minutes of NORMAL data (-20°C) ending 14 hours ago.");
    }

    private function trigger($vend)
    {
        // Toggle between cases here or make dynamic
        // $this->triggerCaseA($vend);
        // $this->triggerCaseB($vend);
        // $this->triggerCaseC($vend);
        $this->triggerCaseD($vend);
    }

    private function triggerCaseA($vend)
    {
        // A) T2 below -25
        $timestamp = now()->subMinutes(18);

        $t1 = new VendTemp();
        $t1->vend_id = $vend->id;
        $t1->type = VendTemp::TYPE_EVAPORATOR;
        $t1->value = -260;
        $t1->created_at = $timestamp;
        $t1->updated_at = $timestamp;
        $t1->save();

        $this->info("Triggered Case A: T2 = -26°C at {$timestamp->toIso8601String()}");
        $this->info("Expected: Alert 'T2 below -25' (Severity 1: > 10 mins).");
    }

    private function triggerCaseB($vend)
    {
        // B) T1 & T2 > 0°C (Warm)
        // Needs duration > 30 mins for severity 1
        // T-45m

        $timestamp = now()->subMinutes(45);

        // T1 = 5.0 C
        $t1 = new VendTemp();
        $t1->vend_id = $vend->id;
        $t1->type = VendTemp::TYPE_CHAMBER;
        $t1->value = 50;
        $t1->created_at = $timestamp;
        $t1->updated_at = $timestamp;
        $t1->save();

        // T2 = 4.0 C
        $t2 = new VendTemp();
        $t2->vend_id = $vend->id;
        $t2->type = VendTemp::TYPE_EVAPORATOR;
        $t2->value = 40;
        $t2->created_at = $timestamp;
        $t2->updated_at = $timestamp;
        $t2->save();

        $this->info("Triggered Case B: Start at {$timestamp->toIso8601String()} (45 mins ago).");
        $this->info("NOTE: We are NOT inserting a 'Current' record.");
        $this->info("This ensures the job sees the 45-min old record as 'Latest' and calculates duration from there.");
    }

    private function triggerCaseC($vend)
    {
        // C) T1 & T2 > -8°C (Defrost)
        // Needs duration > 60 mins for severity 1
        // T-75m (1 hour 15 mins ago)

        $timestamp = now()->subMinutes(75);

        // T1 = -5.0 C (Above -8)
        $t1 = new VendTemp();
        $t1->vend_id = $vend->id;
        $t1->type = VendTemp::TYPE_CHAMBER;
        $t1->value = -50;
        $t1->created_at = $timestamp;
        $t1->updated_at = $timestamp;
        $t1->save();

        // T2 = -4.0 C (Above -8)
        $t2 = new VendTemp();
        $t2->vend_id = $vend->id;
        $t2->type = VendTemp::TYPE_EVAPORATOR;
        $t2->value = -40;
        $t2->created_at = $timestamp;
        $t2->updated_at = $timestamp;
        $t2->save();

        $this->info("Triggered Case C: Start at {$timestamp->toIso8601String()} (75 mins ago).");
        $this->info("NOTE: We are NOT inserting a 'Current' record.");
        $this->info("This ensures the job sees the 75-min old record as 'Latest' and calculates duration from there.");
    }

    private function triggerCaseD($vend)
    {
        // D) T1 & T2 did not reach -18°C
        // Needs duration > 8 hours for severity 1
        // Let's test severity 2 (> 12 hours)
        // T-13 hours

        $timestamp = now()->subHours(13);

        // T1 = -15.0 C (Failed to reach -18)
        $t1 = new VendTemp();
        $t1->vend_id = $vend->id;
        $t1->type = VendTemp::TYPE_CHAMBER;
        $t1->value = -150;
        $t1->created_at = $timestamp;
        $t1->updated_at = $timestamp;
        $t1->save();

        // T2 = -16.0 C (Failed to reach -18)
        $t2 = new VendTemp();
        $t2->vend_id = $vend->id;
        $t2->type = VendTemp::TYPE_EVAPORATOR;
        $t2->value = -160;
        $t2->created_at = $timestamp;
        $t2->updated_at = $timestamp;
        $t2->save();

        $this->info("Triggered Case D: Start at {$timestamp->toIso8601String()} (13 hours ago).");
        $this->info("NOTE: We are NOT inserting a 'Current' record.");
        $this->info("This ensures the job sees the 13-hour old record as 'Latest' and calculates duration from there.");
    }

    private function check($vend)
    {
        // Debug
        $t1 = VendTemp::where('vend_id', $vend->id)->where('type', VendTemp::TYPE_CHAMBER)->latest()->first();
        $t2 = VendTemp::where('vend_id', $vend->id)->where('type', VendTemp::TYPE_EVAPORATOR)->latest()->first();

        $this->info("DEBUG CHECK State:");
        $val1 = $t1 ? $t1->value : 'null';
        $time1 = $t1 ? $t1->created_at->toIso8601String() : 'null';
        $val2 = $t2 ? $t2->value : 'null';
        $time2 = $t2 ? $t2->created_at->toIso8601String() : 'null';

        $this->info("Latest T1: $val1 at $time1");
        $this->info("Latest T2: $val2 at $time2");

        $this->info("Dispatching DetectTempTrends job for Vend ID {$vend->id}...");
        DetectTempTrends::dispatch($vend->id);

        $this->info("Job dispatched to queue.");
        $this->info("If Horizon/Queue is running, check your logs/email.");
    }
}
