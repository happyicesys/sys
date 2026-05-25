<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for the External Subsidize feature.
 *
 * Stamps every EXISTING customer_period_summaries row with the customer's
 * CURRENT External Subsidize value (per user direction: the whole history is
 * locked to "latest, as of now"; only months aggregated AFTER this patch will
 * track future contract changes), then recomputes the NET earnings:
 *
 *   external_subsidize_cents = is_external_subsidize ? ROUND(amount * 100) : 0
 *   location_earning_cents   = gross_earning_cents - (location_fees_cents - external_subsidize_cents)
 *   location_earning_rate    = sales_cents > 0 ? location_earning_cents / sales_cents : 0
 *
 * location_fees_cents (GROSS contract fee) is left untouched.
 *
 * Idempotent: the recompute derives purely from the stable gross_earning_cents
 * / location_fees_cents / sales_cents columns, so re-running converges to the
 * same result.
 *
 *   php artisan db:seed --class=ExternalSubsidizeBackfillSeeder
 */
class ExternalSubsidizeBackfillSeeder extends Seeder
{
    public function run(): void
    {
        // Single set-based UPDATE ... JOIN. The CASE expression for the
        // subsidy cents is repeated because MySQL can't reference a column
        // alias set earlier in the same UPDATE.
        $extSub = 'CASE WHEN c.is_external_subsidize = 1
                        THEN ROUND(COALESCE(c.external_subsidize_amount, 0) * 100)
                        ELSE 0 END';

        $affected = DB::update("
            UPDATE customer_period_summaries s
            JOIN customers c ON c.id = s.customer_id
            SET
                s.external_subsidize_cents = {$extSub},
                s.location_earning_cents = s.gross_earning_cents - (s.location_fees_cents - ({$extSub})),
                s.location_earning_rate = CASE
                    WHEN s.sales_cents > 0
                    THEN ROUND((s.gross_earning_cents - (s.location_fees_cents - ({$extSub}))) / s.sales_cents, 4)
                    ELSE 0 END,
                s.updated_at = NOW()
        ");

        $this->command?->info("ExternalSubsidizeBackfillSeeder: updated {$affected} customer_period_summaries row(s).");
    }
}
