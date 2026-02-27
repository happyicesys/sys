<?php

namespace Database\Seeders;

use App\Models\VendLog;
use App\Models\MachineHealthHistory;
use Illuminate\Database\Seeder;

class MachineHealthHistoryBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Starting backfill of machine health history from vend_logs...');

        $count = 0;
        VendLog::whereIn('event', ['machine_health_alert', 'machine_health_alert_dismissed', 'channel_error'])
            ->chunk(100, function ($logs) use (&$count) {
                foreach ($logs as $log) {
                    $context = $log->context;
                    $event = $log->event;

                    // Normalize channel_error to machine_health_alert for the history table
                    if ($event === 'channel_error') {
                        $event = 'machine_health_alert';
                    }

                    $alertType = $context['alert_type'] ?? $context['type'] ?? ($log->event === 'channel_error' ? 'channel_error' : 'unknown');
                    $bucket = $context['bucket'] ?? ($log->event === 'channel_error' ? 'channel_error' : 'unknown');
                    $severity = $context['severity'] ?? null;

                    MachineHealthHistory::updateOrCreate(
                        [
                            'vend_id' => $log->vend_id,
                            'event' => $event,
                            'alert_type' => $alertType,
                            'bucket' => $bucket,
                            'occurred_at' => $log->occurred_at,
                        ],
                        [
                            'severity' => $severity,
                            'context' => $context,
                        ]
                    );
                    $count++;
                }
                $this->command->getOutput()->write("\rProcessed {$count} records...");
            });

        $this->command->info("\nBackfill finished. Total: {$count} records sync.");
    }
}
