<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendLog;
use App\Services\AlertEmailService;
use App\Support\VendNoTransactionSummary;
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
            ->where('is_sold', false)
            ->get();

        if ($vends->isEmpty()) {
            return self::SUCCESS;
        }

        $overridesHours = $this->option('hours');

        $qualified = $vends->map(function (Vend $vend) use ($now, $overridesHours) {
            $override = $overridesHours !== null ? (int) $overridesHours : null;
            return VendNoTransactionSummary::fromVend($vend, $now, $override);
        })->filter();

        if ($qualified->isEmpty()) {
            return self::SUCCESS;
        }

        $qualified->each(function (array $summary) use ($now) {
            VendLog::create([
                'vend_id' => $summary['id'],
                'event' => VendLog::EVENT_NO_TRANSACTION,
                'subject' => sprintf('No transactions for >= %s hrs', $summary['threshold_hours']),
                'context' => [
                    'threshold_hours' => $summary['threshold_hours'],
                    'hours_since_last_transaction' => $summary['hours_since_last_transaction'],
                    'last_transaction_at' => $summary['last_transaction_at'],
                    'triggered_types' => collect($summary['triggered_types'] ?? [])->pluck('label')->all(),
                ],
                'occurred_at' => $now,
            ]);
        });

        $byOperator = $qualified->groupBy(function (array $summary) {
            return $summary['operator_id'] ?? 'global';
        });

        foreach ($byOperator as $operatorKey => $vendSummaries) {
            $operator = $operatorKey === 'global'
                ? null
                : Operator::find((int) $operatorKey);

            $this->alertEmailService->sendVendTransactionNoEntryNotificationMail(
                $operator,
                $vendSummaries->map(fn($summary) => Arr::except($summary, ['operator_id']))
            );
        }

        return self::SUCCESS;
    }
}
