<?php

namespace App\Console\Commands;

use App\Mail\VendTransactionNoEntryNotificationMail;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use App\Support\VendNoTransactionSummary;

class TestVendTransactionNoEntryEmail extends Command
{
    protected $signature = 'test:vend-transaction-no-entry-email
        {email : Recipient email address}
        {--operator= : Restrict to a specific operator ID}
        {--hours= : Override the threshold hours check}
        {--vend= : Restrict to a single vend code (comma separated for multiple)}';

    protected $description = 'Manually trigger the "no transaction" email to a specific address for testing';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $userOperatorId = User::query()
            ->where('email', $email)
            ->value('operator_id');

        $operatorId = $this->option('operator') ?? $userOperatorId;

        if ($this->option('operator') === null && $userOperatorId !== null) {
            $this->info(sprintf('Auto-detected operator %s from user %s', $userOperatorId, $email));
        }
        $hoursOverride = $this->option('hours');
        $vendFilter = $this->parseVendCodes((string) $this->option('vend'));
        $now = Carbon::now();

        $query = Vend::query()
            ->with(['operator:id,name,code', 'customer:id,code,name', 'vendPrefix:id,name', 'alertSetting'])
            ->where('is_active', true)
            ->where('is_disposed', false)
            ->where('is_testing', false);

        if ($operatorId !== null) {
            $query->where('operator_id', $operatorId);
        }

        if ($vendFilter->isNotEmpty()) {
            $query->whereIn('code', $vendFilter->all());
        }

        $vends = $query->get();

        if ($vends->isEmpty()) {
            $this->warn('No machines matched the criteria.');
            return self::FAILURE;
        }

        $qualified = $vends->map(function (Vend $vend) use ($now, $hoursOverride) {
            $override = $hoursOverride !== null ? (int) $hoursOverride : null;
            return VendNoTransactionSummary::fromVend($vend, $now, $override);
        })->filter();

        if ($qualified->isEmpty()) {
            $this->warn('No machines exceeded the threshold with the current filters.');
            return self::FAILURE;
        }

        $byOperator = $qualified->groupBy(fn (array $summary) => $summary['operator_id'] ?? 'global');
        $sentCount = 0;

        foreach ($byOperator as $operatorKey => $vendSummaries) {
            if ($operatorId !== null && (string) $operatorKey !== (string) $operatorId) {
                continue;
            }

            $operator = $operatorKey === 'global'
                ? null
                : Operator::find((int) $operatorKey);

            $payload = $vendSummaries
                ->map(fn ($summary) => Arr::except($summary, ['operator_id']))
                ->values()
                ->all();

            Mail::to($email)->send(new VendTransactionNoEntryNotificationMail($operator?->id, $payload));
            $sentCount++;

            $this->info(sprintf(
                'Sent test email for operator %s (%d machines) to %s',
                $operator?->code ?? 'GLOBAL',
                count($payload),
                $email
            ));
        }

        if ($sentCount === 0) {
            $this->warn('No operator group matched the provided filters.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function parseVendCodes(?string $raw)
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($code) => trim($code))
            ->filter();
    }
}
