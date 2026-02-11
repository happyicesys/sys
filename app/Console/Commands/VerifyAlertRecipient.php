<?php

namespace App\Console\Commands;

use App\Models\AlertEmailItem;
use App\Models\Vend;
use App\Models\Operator;
use Illuminate\Console\Command;

class VerifyAlertRecipient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:alert-recipient {email : The email address to verify} {vend_code : The vend code or ID to check against}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify if a specific email is configured to receive alerts for a given vend.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = strtolower(trim($this->argument('email')));
        $vendCode = $this->argument('vend_code');

        $this->info("Verifying alert configuration for email: '{$email}' on vend: '{$vendCode}'");

        $vend = Vend::where('code', $vendCode)->orWhere('id', $vendCode)->with('operator')->first();

        if (!$vend) {
            $this->error("Vend '{$vendCode}' not found.");
            return self::FAILURE;
        }

        $operator = $vend->operator;
        if (!$operator) {
            $this->error("Vend '{$vend->code}' (ID: {$vend->id}) has NO operator assigned.");
            $this->warn("Only 'Global' recipients (operator_id=NULL) would receive alerts for this vend.");
        } else {
            $this->info("Vend '{$vend->code}' (ID: {$vend->id}) belongs to Operator: '{$operator->name}' (ID: {$operator->id}, Code: {$operator->code})");
        }

        $operatorId = $operator?->id;

        // Query AlertEmailItems
        $items = AlertEmailItem::query()
            ->where('email', $email)
            ->where(function ($q) use ($operatorId) {
                $q->whereNull('operator_id')
                    ->orWhere('operator_id', $operatorId);
            })
            ->get();

        if ($items->isEmpty()) {
            $this->error("X No AlertEmailItem found for '{$email}' that matches Operator ID {$operatorId} (or Global).");
            $this->line("Suggestions:");
            $this->line("1. Go to Operator/Edit.vue for Operator '{$operator->name}'.");
            $this->line("2. Ensure '{$email}' is selected in 'Machine Email Alert User(s)'.");
            $this->line("3. Save the operator.");
            return self::FAILURE;
        }

        $this->info("Found " . $items->count() . " matching AlertEmailItem(s):");

        foreach ($items as $item) {
            $scope = $item->operator_id ? "Operator Specific (ID: {$item->operator_id})" : "Global (All Operators)";
            $status = $item->is_active ? "ACTIVE" : "INACTIVE";

            $this->line("--------------------------------------------------");
            $this->line("Scope: {$scope}");
            $this->line("Status: {$status}");
            $this->line("Flags:");
            $this->line(" - Channel Error: " . ($item->is_send_channel_error_log ? 'YES' : 'NO'));
            $this->line(" - Offline: " . ($item->is_send_offline_notification ? 'YES' : 'NO'));
            $this->line(" - Power Restored: " . ($item->is_send_power_restored_notification ? 'YES' : 'NO'));
            $this->line(" - No Transaction: " . ($item->is_send_transaction_no_entry_notification ? 'YES' : 'NO'));

            if (!$item->is_active) {
                $this->warn("! This recipient is configured but marked INACTIVE. They will NOT receive emails.");
            } elseif (!$item->is_send_channel_error_log) { // Assuming checking for general error/offline
                $this->warn("! 'is_send_channel_error_log' is OFF. They might miss Operation Error alerts.");
            } else {
                $this->info("✓ Configuration looks CORRECT for this item.");
            }
        }

        return self::SUCCESS;
    }
}
