<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Models\Vend;
use App\Services\AlertEmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SendVendTransactionNoEntryEmail extends Command
{
    protected $signature = 'send:vend-transaction-no-entry-email {--hours=}';
    protected $description = 'Send aggregated machines with no transactions beyond configured thresholds';

    public function __construct(protected AlertEmailService $alertEmailService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now();

        $vends = Vend::query()
            ->with(['operator:id,name,code', 'customer:id,code,name', 'vendPrefix:id,name', 'alertSetting'])
            ->where('is_active', true)
            ->where('is_disposed', false)
            ->where('is_testing', false)
            ->get();

        if ($vends->isEmpty()) {
            return self::SUCCESS;
        }

        $overridesHours = $this->option('hours');

        $qualified = $vends->map(function (Vend $vend) use ($now, $overridesHours) {
            $thresholdHours = $overridesHours !== null
                ? (int) $overridesHours
                : (int) $vend->noSalesAlertHours();

            if ($thresholdHours <= 0) {
                return null;
            }

            $lastTransaction = $vend->last_vend_transaction_at;

            if (!$lastTransaction) {
                return null;
            }

            $diffMinutes = $lastTransaction->diffInMinutes($now);
            if ($diffMinutes < $thresholdHours * 60) {
                return null;
            }
            $hoursSince = round($diffMinutes / 60, 2);

            return [
                'id' => $vend->id,
                'code' => $vend->code,
                'name' => $vend->name,
                'operator_id' => $vend->operator_id,
                'vend_prefix_name' => $vend->vendPrefix?->name,
                'customer' => $vend->customer ? [
                    'code' => $vend->customer->code,
                    'name' => $vend->customer->name,
                ] : null,
                'threshold_hours' => $thresholdHours,
                'hours_since_last_transaction' => $hoursSince,
                'last_transaction_at' => $lastTransaction?->toIso8601String(),
            ];
        })->filter();

        if ($qualified->isEmpty()) {
            return self::SUCCESS;
        }

        $byOperator = $qualified->groupBy(function (array $summary) {
            return $summary['operator_id'] ?? 'global';
        });

        foreach ($byOperator as $operatorKey => $vendSummaries) {
            $operator = $operatorKey === 'global'
                ? null
                : Operator::find((int) $operatorKey);

            $this->alertEmailService->sendVendTransactionNoEntryNotificationMail(
                $operator,
                $vendSummaries->map(fn ($summary) => Arr::except($summary, ['operator_id']))
            );
        }

        return self::SUCCESS;
    }
}
