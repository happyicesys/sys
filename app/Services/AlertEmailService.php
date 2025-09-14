<?php

namespace App\Services;

use App\Mail\VendChannelErrorLogsMail;
use App\Mail\VendOfflineNotificationMail;
use App\Mail\VendPowerRestoredNotificationMail;
use App\Models\AlertEmailItem;
use App\Models\Operator;
use App\Models\Vend;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

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
            return 0;
        }

        foreach ($emails as $email) {
            Mail::to($email)->queue(new VendChannelErrorLogsMail($operator, $vendErrorsByVend));
        }

        return $emails->count();
    }

    /**
     * OFFLINE: per-vend email (no aggregation).
     */
    public function sendVendOfflineNotificationMail(Vend $vend): int
    {
        $emails = $this->recipients($vend->operator, 'is_send_offline_notification');
        if ($emails->isEmpty()) {
            return 0;
        }

        foreach ($emails as $email) {
            Mail::to($email)->queue(new VendOfflineNotificationMail($vend));
        }

        return $emails->count();
    }

    /**
     * POWER RESTORED: per-vend email (no aggregation).
     */
    public function sendVendPowerRestoredNotificationMail(Vend $vend): int
    {
        $emails = $this->recipients($vend->operator, 'is_send_power_restored_notification');
        if ($emails->isEmpty()) {
            return 0;
        }

        foreach ($emails as $email) {
            Mail::to($email)->queue(new VendPowerRestoredNotificationMail($vend));
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
}
