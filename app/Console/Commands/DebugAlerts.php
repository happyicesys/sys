<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Support\VendNoTransactionSummary;
use App\Models\AlertEmailItem;
use App\Models\Operator;
use App\Models\VendChannelErrorLog;
use Carbon\Carbon;

class DebugAlerts extends Command
{
    protected $signature = 'debug:alerts';
    protected $description = 'Debug why transaction and channel error alerts might not be sending';

    public function handle()
    {
        $this->info("========================================");
        $this->info(" DEBUG ALERT CONFIGURATION");
        $this->info("========================================");
        $this->info("App Timezone: " . config('app.timezone'));
        $this->info("System Date: " . date('Y-m-d H:i:s'));
        $this->info("Carbon Now: " . Carbon::now()->toDateTimeString());

        $this->checkNoTransactionAlerts();
        $this->checkChannelErrorAlerts();

        return 0;
    }

    protected function checkNoTransactionAlerts()
    {
        $this->info("\n----------------------------------------");
        $this->info(" CHECKING: Machines Without Transactions");
        $this->info("----------------------------------------");

        $vends = Vend::query()
            ->with(['operator'])
            ->where('is_active', true)
            ->where('is_disposed', false)
            ->where('is_testing', false)
            ->get();

        $this->info("Total Active Vends Scanned: " . $vends->count());

        $now = Carbon::now();
        $qualified = $vends->map(function (Vend $vend) use ($now) {
            return VendNoTransactionSummary::fromVend($vend, $now);
        })->filter();

        $this->info("Qualified Vends (Triggering Alert): " . $qualified->count());

        if ($qualified->isEmpty()) {
            $this->warn("Result: No emails would be sent because no machines meet the 'No Transaction' criteria.");
            return;
        }

        $byOperator = $qualified->groupBy(fn($s) => $s['operator_id'] ?? 'global');

        foreach ($byOperator as $operatorKey => $summaries) {
            $operator = $operatorKey === 'global' ? null : Operator::find($operatorKey);
            $name = $operator ? "Operator: {$operator->name} ({$operator->code})" : "Global (No Operator)";

            $this->info("\n> Group: $name");
            $this->info("  vends count: " . $summaries->count());

            $recipients = $this->getRecipients($operator?->id, 'is_send_transaction_no_entry_notification');

            if ($recipients->isEmpty()) {
                $this->error("  ERROR: No active recipients found with 'is_send_transaction_no_entry_notification' enabled!");
            } else {
                $this->info("  Recipients: " . $recipients->implode(', '));
            }
        }
    }

    protected function checkChannelErrorAlerts()
    {
        $this->info("\n----------------------------------------");
        $this->info(" CHECKING: Channel Error Logs (Last 24h)");
        $this->info("----------------------------------------");

        $since = Carbon::now()->subHours(24);
        $logs = VendChannelErrorLog::with(['vendChannel.vend'])
            ->where('created_at', '>=', $since)
            ->where('is_error_cleared', false)
            ->get();

        $this->info("Total Uncleared Error Logs (Last 24h): " . $logs->count());

        if ($logs->isEmpty()) {
            $this->warn("Result: No emails would be sent because there are no uncleared error logs in the last 24 hours.");
            return;
        }

        // Filter out logs where vend relation is missing
        $validLogs = $logs->filter(fn($log) => $log->vendChannel?->vend);

        $byOperator = $validLogs->groupBy(fn($log) => $log->vendChannel->vend->operator_id);

        foreach ($byOperator as $operatorId => $opLogs) {
            $operator = $operatorId ? Operator::find($operatorId) : null;
            $name = $operator ? "Operator: {$operator->name}" : "Global / No Operator";

            $this->info("\n> Group: $name");
            $this->info("  Error Logs count: " . $opLogs->count());

            $recipients = $this->getRecipients($operatorId, 'is_send_channel_error_log');

            if ($recipients->isEmpty()) {
                $this->error("  ERROR: No active recipients found with 'is_send_channel_error_log' enabled!");
            } else {
                $this->info("  Recipients: " . $recipients->implode(', '));
            }
        }
    }

    protected function getRecipients($operatorId, $flag)
    {
        return AlertEmailItem::query()
            ->where('is_active', true)
            ->where($flag, true)
            ->where(function ($q) use ($operatorId) {
                $q->whereNull('operator_id')
                    ->orWhere('operator_id', $operatorId);
            })
            ->pluck('email');
    }
}
