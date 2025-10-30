<?php

namespace App\Support;

use App\Models\Vend;
use Carbon\Carbon;

class VendNoTransactionSummary
{
    public static function fromVend(Vend $vend, Carbon $now, ?int $overrideThresholdHours = null): ?array
    {
        $thresholdHours = $overrideThresholdHours !== null
            ? (int) $overrideThresholdHours
            : (int) $vend->noSalesAlertHours();

        if ($thresholdHours <= 0) {
            return null;
        }

        $typeTimestamps = [
            'Transaction' => $vend->last_vend_transaction_at,
            'Cash' => $vend->last_cash_vend_transaction_at,
            'Card' => $vend->last_card_vend_transaction_at,
            'Cashless' => $vend->last_cashless_vend_transaction_at,
        ];

        $typeDetails = collect($typeTimestamps)->map(function ($timestamp, $label) use ($now, $thresholdHours) {
            if (!$timestamp) {
                return null;
            }

            $diffMinutes = $timestamp->diffInMinutes($now);
            $hoursSince = round($diffMinutes / 60, 2);

            return [
                'label' => $label,
                'timestamp' => $timestamp->toIso8601String(),
                'hours_since' => $hoursSince,
                'triggered' => $hoursSince >= $thresholdHours,
            ];
        })->filter();

        if ($typeDetails->isEmpty()) {
            return null;
        }

        $triggeredDetails = $typeDetails->filter(fn ($detail) => $detail['triggered']);

        $transactionDetail = $typeDetails->firstWhere('label', 'Transaction');
        if ($transactionDetail && !$transactionDetail['triggered']) {
            return null;
        }

        if ($triggeredDetails->isEmpty()) {
            return null;
        }

        $primaryDetail = $triggeredDetails->firstWhere('label', 'Transaction')
            ?? $triggeredDetails->sortByDesc('hours_since')->first();

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
            'hours_since_last_transaction' => $primaryDetail['hours_since'],
            'last_transaction_at' => $primaryDetail['timestamp'],
            'transaction_types' => $typeDetails->values()->all(),
            'triggered_types' => $triggeredDetails->values()->all(),
        ];
    }
}
