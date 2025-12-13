<?php

namespace App\Services;

use App\Mail\VendChannelErrorLogsMail;
use App\Mail\VendOfflineNotificationMail;
use App\Mail\VendPowerRestoredNotificationMail;
use App\Mail\VendTransactionNoEntryNotificationMail;
use App\Models\AlertEmailItem;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AlertEmailService
{
    /**
     * CHANNEL ERROR: ONE email per recipient containing ALL error vends for the operator.
     * $vendErrorsByVend: Collection keyed by vend_id => Collection/array of error logs
     */
    public function sendChannelErrorLogsForOperator(?Operator $operator, Collection $vendErrorsByVend): int
    {
        $emails = $this->recipients($operator, 'is_send_channel_error_log');
        if ($emails->isEmpty() || $vendErrorsByVend->isEmpty()) {
            Log::info('AlertEmail: no recipients or empty errors for VendChannelErrorLogsMail', [
                'operator_id' => $operator?->id,
                'recipient_count' => $emails->count(),
                'vend_count' => $vendErrorsByVend->count(),
            ]);
            return 0;
        }

        $vendCount = $vendErrorsByVend->count();
        Log::info('AlertEmail: queuing VendChannelErrorLogsMail', [
            'operator_id' => $operator?->id,
            'recipient_count' => $emails->count(),
            'vend_count' => $vendCount,
            'recipients' => $emails->all(),
        ]);
        foreach ($emails as $email) {
            Mail::to($email)->queue(new VendChannelErrorLogsMail($operator, $vendErrorsByVend));
            Log::info('AlertEmail: queued VendChannelErrorLogsMail', [
                'operator_id' => $operator?->id,
                'recipient' => $email,
                'vend_count' => $vendCount,
            ]);
        }

        // Send Callback
        if ($operator) {
            $callback = $operator->operatorCallbacks()->where('code', 'channel_error_alert')->first();
            if ($callback) {
                \App\Jobs\SendOperatorCallback::dispatch($callback->url, [
                    'event' => 'channel_error_alert',
                    'operator_id' => $operator->id,
                    'occurred_at' => now()->toIso8601String(),
                    'errors' => $vendErrorsByVend->toArray(), // Beware of size
                    'vend_count' => $vendCount,
                ])->onQueue('default');
            }
        }

        return $emails->count();
    }

    /**
     * OFFLINE: per-vend email (no aggregation).
     */
    public function sendVendOfflineNotificationMail(Vend $vend): int
    {
        $thresholdMinutes = $vend->offlineAlertMinutes();

        $recipientCount = $this->queueVendNotification(
            $vend,
            'is_send_offline_notification',
            fn() => new VendOfflineNotificationMail((int) $vend->getKey(), $thresholdMinutes),
            'VendOfflineNotificationMail',
            [
                'threshold_minutes' => $thresholdMinutes,
            ]
        );

        if ($recipientCount > 0) {
            VendLog::create([
                'vend_id' => $vend->id,
                'event' => VendLog::EVENT_POWER_OFF,
                'subject' => sprintf('Offline alert queued (%d recipients)', $recipientCount),
                'context' => [
                    'threshold_minutes' => $thresholdMinutes,
                    'recipients' => $recipientCount,
                ],
                'occurred_at' => now(),
            ]);
        }

        // Send Callback
        if ($vend->operator) {
            $callback = $vend->operator->operatorCallbacks()->where('code', 'vend_offline_alert')->first();
            if ($callback) {
                \App\Jobs\SendOperatorCallback::dispatch($callback->url, [
                    'event' => 'vend_offline_alert',
                    'vend_code' => $vend->code,
                    'occurred_at' => now()->toIso8601String(),
                    'threshold_minutes' => $thresholdMinutes,
                ])->onQueue('default');
            }
        }

        return $recipientCount;
    }

    /**
     * POWER RESTORED: per-vend email (no aggregation).
     */
    public function sendVendPowerRestoredNotificationMail(Vend $vend): int
    {
        $thresholdMinutes = $vend->powerRestoredAlertMinutes();

        $recipientCount = $this->queueVendNotification(
            $vend,
            'is_send_power_restored_notification',
            fn() => new VendPowerRestoredNotificationMail((int) $vend->getKey(), $thresholdMinutes),
            'VendPowerRestoredNotificationMail',
            [
                'threshold_minutes' => $thresholdMinutes,
            ]
        );

        if ($recipientCount > 0) {
            VendLog::create([
                'vend_id' => $vend->id,
                'event' => VendLog::EVENT_POWER_RESTORED,
                'subject' => sprintf('Power restored alert queued (%d recipients)', $recipientCount),
                'context' => [
                    'threshold_minutes' => $thresholdMinutes,
                    'recipients' => $recipientCount,
                ],
                'occurred_at' => now(),
            ]);
        }

        // Send Callback
        if ($vend->operator) {
            $callback = $vend->operator->operatorCallbacks()->where('code', 'vend_power_restored_alert')->first();
            if ($callback) {
                \App\Jobs\SendOperatorCallback::dispatch($callback->url, [
                    'event' => 'vend_power_restored_alert',
                    'vend_code' => $vend->code,
                    'occurred_at' => now()->toIso8601String(),
                    'threshold_minutes' => $thresholdMinutes,
                ])->onQueue('default');
            }
        }

        return $recipientCount;
    }

    /**
     * TRANSACTION NO ENTRY: aggregated per-operator email containing all machines that exceeded threshold.
     *
     * @param \App\Models\Operator|null $operator
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $vendSummaries
     */
    public function sendVendTransactionNoEntryNotificationMail(?Operator $operator, Collection $vendSummaries): int
    {
        $vendSummaries = $vendSummaries->filter(function ($summary) {
            $threshold = (int) ($summary['threshold_hours'] ?? 0);
            return $threshold > 0;
        });

        if ($vendSummaries->isEmpty()) {
            Log::info('AlertEmail: no machines met criteria for VendTransactionNoEntryNotificationMail', [
                'operator_id' => $operator?->id,
            ]);
            return 0;
        }

        $emails = $this->recipients($operator, 'is_send_transaction_no_entry_notification');

        $baseContext = [
            'operator_id' => $operator?->id,
            'vend_count' => $vendSummaries->count(),
            'mail' => 'VendTransactionNoEntryNotificationMail',
        ];

        if ($emails->isEmpty()) {
            Log::info('AlertEmail: no recipients for VendTransactionNoEntryNotificationMail', $baseContext);
            return 0;
        }

        Log::info('AlertEmail: queuing VendTransactionNoEntryNotificationMail', array_merge($baseContext, [
            'recipient_count' => $emails->count(),
            'recipients' => $emails->all(),
        ]));

        $payload = $vendSummaries
            ->map(fn($summary) => $summary)
            ->values()
            ->all();

        foreach ($emails as $email) {
            Mail::to($email)->queue(new VendTransactionNoEntryNotificationMail($operator?->id, $payload));
            Log::info('AlertEmail: queued VendTransactionNoEntryNotificationMail', array_merge($baseContext, [
                'recipient' => $email,
            ]));
        }

        return $emails->count();
    }

    /**
     * Helper: resolve recipient emails (global + operator-specific) & dedupe.
     */
    protected function recipients(?Operator $operator, string $flag): Collection
    {
        $operatorId = $operator?->id;

        return AlertEmailItem::query()
            ->where('is_active', true)
            ->where($flag, true)
            ->where(function ($q) use ($operatorId) {
                $q->whereNull('operator_id')
                    ->orWhere('operator_id', $operatorId);
            })
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Helper: queue per-vend notification emails with shared logging.
     *
     * @param callable(): \Illuminate\Mail\Mailable $mailableFactory
     */
    protected function queueVendNotification(
        Vend $vend,
        string $flag,
        callable $mailableFactory,
        string $logKey,
        array $context = []
    ): int {
        $emails = $this->recipients($vend->operator, $flag);

        $baseContext = array_merge([
            'vend_id' => $vend->id,
            'operator_id' => $vend->operator_id,
            'mail' => $logKey,
        ], $context);

        if ($emails->isEmpty()) {
            Log::info("AlertEmail: no recipients for {$logKey}", $baseContext);
            return 0;
        }

        Log::info("AlertEmail: queuing {$logKey}", array_merge($baseContext, [
            'recipient_count' => $emails->count(),
            'recipients' => $emails->all(),
        ]));

        foreach ($emails as $email) {
            Mail::to($email)->queue($mailableFactory());
            Log::info("AlertEmail: queued {$logKey}", array_merge($baseContext, [
                'recipient' => $email,
            ]));
        }

        return $emails->count();
    }
}
