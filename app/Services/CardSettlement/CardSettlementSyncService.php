<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\VendTransaction;

/**
 * Stamps card_settlement_synced_at onto every sale matched by a report.
 *
 * Chunked UPDATE ... WHERE id IN (...) so a full day's report (~3.5k rows)
 * never holds a long row lock on the 4.6M-row table. Idempotent: an already
 * stamped sale keeps its original stamp, and re-running after more rows are
 * resolved only touches the new ones.
 */
class CardSettlementSyncService
{
    const CHUNK = 500;

    /** @return int number of matched rows covered by the sync */
    public function sync(CardSettlementReport $report, ?int $userId): int
    {
        $txnIds = $report->rows()
            ->where('status', CardSettlementRow::STATUS_MATCHED)
            ->whereNotNull('matched_vend_transaction_id')
            ->pluck('matched_vend_transaction_id');

        foreach ($txnIds->chunk(self::CHUNK) as $chunk) {
            VendTransaction::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $chunk)
                ->whereNull('card_settlement_synced_at')
                ->update(['card_settlement_synced_at' => now()]);
        }

        $report->forceFill([
            'status' => CardSettlementReport::STATUS_SYNCED,
            'synced_count' => $txnIds->count(),
            'synced_at' => now(),
            'synced_by' => $userId,
        ])->save();

        return $txnIds->count();
    }
}
