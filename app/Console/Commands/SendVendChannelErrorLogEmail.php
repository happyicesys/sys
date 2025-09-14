<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Models\VendChannelErrorLog;
use App\Services\AlertEmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendVendChannelErrorLogEmail extends Command
{
    protected $signature = 'send:channel-error-logs-email {--hours=24}';
    protected $description = 'Send aggregated vending channel error logs emails (grouped by Operator)';

    public function __construct(protected AlertEmailService $alertEmailService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $intervalHours = (int) $this->option('hours') ?: 24;
        $since = Carbon::now()->subHours($intervalHours);

        $logs = VendChannelErrorLog::with([
                'vendChannel',
                'vendChannel.vend',
                'vendChannel.vend.operator',
                'vendChannel.vend.customer',
                'vendChannelError',
            ])
            ->where('created_at', '>=', $since)
            ->where('is_error_cleared', false)
            ->get();

        if ($logs->isEmpty()) {
            return self::SUCCESS;
        }

        $byOperator = $logs
            ->filter(fn ($log) => $log->vendChannel?->vend)
            ->groupBy(fn ($log) => $log->vendChannel->vend->operator_id);

        foreach ($byOperator as $operatorId => $opLogs) {
            $vendGroups = $opLogs
                ->groupBy(fn ($log) => $log->vendChannel->vend_id)
                ->map(fn ($vendLogs) => $vendLogs->sortBy('created_at')->values());

            $vendGroups = $vendGroups->sortBy(function ($vendLogs) {
                $vend = $vendLogs->first()->vendChannel->vend;
                return sprintf('%08s', (string) ($vend->code ?? ''));
            });

            $operator = $operatorId ? Operator::find($operatorId) : null;

            $this->alertEmailService->sendChannelErrorLogsForOperator($operator, $vendGroups);
        }

        return self::SUCCESS;
    }
}
