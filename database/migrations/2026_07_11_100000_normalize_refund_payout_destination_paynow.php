<?php

use App\Models\RefundTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill: bring existing PayNow payout_destination values into the same
 * canonical 8-digit shape the new mutator now enforces on write. Only rows whose
 * value actually changes are touched, and updated_at is left alone (no audit noise).
 * Non-mobile proxies (PayPal emails, NRIC, UEN) are untouched by the normalizer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('refund_tickets')
            ->whereNotNull('payout_destination')
            ->where('payout_destination', '!=', '')
            ->orderBy('id')
            ->select(['id', 'payout_destination'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = RefundTicket::normalizePaynowMobile($row->payout_destination);
                    if ($normalized !== null && $normalized !== $row->payout_destination) {
                        DB::table('refund_tickets')
                            ->where('id', $row->id)
                            ->update(['payout_destination' => $normalized]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Non-reversible: original free-text formats are not retained.
    }
};
