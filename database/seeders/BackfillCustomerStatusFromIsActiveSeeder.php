<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for the Customer "Status" field.
 *
 * The Customer/Edit page used to expose a boolean "Is Active?" (Yes/No)
 * stored on customers.is_active. That field was replaced by a 5-value
 * "Status" dropdown (Potential / New / Active / Pending / Inactive) stored
 * on customers.status_id.
 *
 * This seeder initialises status_id for EXISTING customers from their old
 * is_active value:
 *     is_active = true  -> STATUS_ACTIVE   (Active)
 *     is_active = false -> STATUS_INACTIVE (Inactive)
 *
 * Customers that should be Potential / New / Pending are left for the user
 * to update manually afterwards.
 *
 * is_active is kept as a derived mirror going forward
 * (is_active = status_id === STATUS_ACTIVE), so re-running this seeder is
 * idempotent and safe.
 *
 * Run with: php artisan db:seed --class=BackfillCustomerStatusFromIsActiveSeeder
 */
class BackfillCustomerStatusFromIsActiveSeeder extends Seeder
{
    public function run(): void
    {
        // Active: is_active = true  -> status_id = STATUS_ACTIVE (2)
        $activeCount = DB::table('customers')
            ->where('is_active', true)
            ->update(['status_id' => Customer::STATUS_ACTIVE]);

        // Inactive: is_active = false -> status_id = STATUS_INACTIVE (1)
        $inactiveCount = DB::table('customers')
            ->where('is_active', false)
            ->update(['status_id' => Customer::STATUS_INACTIVE]);

        $this->command?->info(
            "Customer status backfill complete: "
            . "{$activeCount} -> Active, {$inactiveCount} -> Inactive."
        );
    }
}
