<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\AlertEmailItem;
use App\Services\AlertEmailService;
use Illuminate\Console\Command;

class ValidateAlerts extends Command
{
    protected $signature = 'validate:alerts {vend_code}';
    protected $description = 'Manually trigger offline/temp alerts for a vend to validate recipients.';

    public function handle()
    {
        $vendCode = $this->argument('vend_code');
        $vend = Vend::where('code', $vendCode)->orWhere('id', $vendCode)->with('operator')->first();

        if (!$vend) {
            $this->error("Vend $vendCode not found.");
            return 1;
        }

        $this->info("Validating alerts for Vend: {$vend->code} (Operator: {$vend->operator?->name})");

        $service = new AlertEmailService();

        // 1. Simulate Offline Alert
        $this->info("\n--- Simulating Offline Alert (> 12hr) ---");
        // We can't easily capture the internal recipients from the service call without modifying it,
        // but we can reproduce the query to show who *should* get it.
        $offlineRecipients = AlertEmailItem::where('is_active', true)
            ->where('is_send_offline_notification', true)
            ->where(function ($q) use ($vend) {
                $q->whereNull('operator_id')
                    ->orWhere('operator_id', $vend->operator_id);
            })
            ->pluck('email');

        $this->info("Expected Recipients (Offline): " . $offlineRecipients->implode(', '));

        $count = $service->sendVendOfflineNotificationMail($vend, '> 12hr');
        $this->info("Service reported queuing for $count recipients.");

        // 2. Simulate Temp 2.1 Alert
        $this->info("\n--- Simulating Temp 2.1 Alert (T2 < -25C, > 30 mins) ---");
        $tempRecipients = AlertEmailItem::where('is_active', true)
            ->where('is_send_channel_error_log', true) // Using this flag as per service code
            ->where(function ($q) use ($vend) {
                $q->whereNull('operator_id')
                    ->orWhere('operator_id', $vend->operator_id);
            })
            ->pluck('email');

        $this->info("Expected Recipients (Temp/Error): " . $tempRecipients->implode(', '));

        $count2 = $service->sendVendOperationErrorNotificationMail($vend, 't2_below_minus_25', '> 30 mins');
        $this->info("Service reported queuing for $count2 recipients.");

        if ($offlineRecipients->contains('leehongjie91@gmail.com')) {
            $this->info("\nPASS: leehongjie91@gmail.com is in the Offline list.");
        } else {
            $this->error("\nFAIL: leehongjie91@gmail.com is NOT in the Offline list.");
        }

        if ($tempRecipients->contains('leehongjie91@gmail.com')) {
            $this->info("PASS: leehongjie91@gmail.com is in the Temp/Error list.");
        } else {
            $this->error("FAIL: leehongjie91@gmail.com is NOT in the Temp/Error list.");
        }

        return 0;
    }
}
