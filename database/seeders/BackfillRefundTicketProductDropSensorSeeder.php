<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for the Refund Index "Prod Exit Sensor" column.
 *
 * vend_transactions.product_drop_sensor is captured/frozen at transaction time
 * going forward, so refund tickets created before that change show "—". This
 * seeder fills the column for the transactions that refund tickets actually
 * read (refund_tickets.vend_transaction_id).
 *
 * IMPORTANT CAVEAT — best-effort, not a true historical snapshot:
 *   The sensor state at the ORIGINAL moment of each past transaction is not
 *   stored anywhere. This backfill therefore uses the machine's CURRENT
 *   parameter_json->Sensor as a proxy (odd = Enabled, even = Disabled, absent
 *   / non-numeric = NULL/unknown). Because the sensor enable/disable setting
 *   rarely changes, this is a reasonable approximation, but a row backfilled
 *   here can differ from what the sensor truly was at purchase time. Rows
 *   captured live (post-deploy) are exact; only these seeded rows are proxied.
 *
 * Idempotent & non-destructive: only rows where product_drop_sensor IS NULL
 * are touched, so re-running never overwrites a genuinely-captured (or
 * already-seeded) value. Scoped to refund-matched transactions only — the rest
 * of vend_transactions is intentionally left untouched.
 *
 *   php artisan db:seed --class=BackfillRefundTicketProductDropSensorSeeder
 */
class BackfillRefundTicketProductDropSensorSeeder extends Seeder
{
    public function run(): void
    {
        // Multi-table UPDATE join to vends. Sensor lives on vends.parameter_json
        // as a number (odd = Enabled). Guard against absent / non-numeric values
        // by leaving them NULL (unknown) rather than coercing to false.
        $affected = DB::update("
            UPDATE vend_transactions vt
            JOIN vends v ON v.id = vt.vend_id
            SET vt.product_drop_sensor = CASE
                WHEN JSON_UNQUOTE(JSON_EXTRACT(v.parameter_json, '$.Sensor')) REGEXP '^[0-9]+$'
                    THEN (CAST(JSON_UNQUOTE(JSON_EXTRACT(v.parameter_json, '$.Sensor')) AS UNSIGNED) % 2 = 1)
                ELSE NULL
            END
            WHERE vt.product_drop_sensor IS NULL
              AND vt.vend_id IS NOT NULL
              AND vt.id IN (
                  SELECT vend_transaction_id
                  FROM refund_tickets
                  WHERE vend_transaction_id IS NOT NULL
              )
        ");

        $this->command?->info(sprintf(
            'Backfilled product_drop_sensor on %d refund-matched vend_transactions row(s) ' .
            '(from the machine\'s current Sensor setting — best-effort proxy for history).',
            $affected
        ));
    }
}
