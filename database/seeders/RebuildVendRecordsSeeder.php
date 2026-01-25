<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VendRecord;
use App\Models\Customer;
use App\Jobs\StoreVendsRecord;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use Carbon\Carbon;

class RebuildVendRecordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Define the date range to rebuild
        // Defaulting to 90 days to ensure all quarterly data/charts are correct
        $daysToRebuild = 90;
        $fromDate = Carbon::today()->subDays($daysToRebuild)->toDateString();
        $toDate = Carbon::today()->toDateString();

        $this->command->info("Rebuilding VendRecords from $fromDate to $toDate...");

        // 1. Delete existing records for the period
        $this->command->info("Deleting existing VendRecords...");
        VendRecord::whereBetween('date', [$fromDate, $toDate])->delete();

        // 2. Dispatch the StoreVendsRecord job synchronously
        $this->command->info("Dispatching StoreVendsRecord (this may take a while)...");
        StoreVendsRecord::dispatchSync($fromDate, $toDate, true);

        // 3. Update Customer totals caches
        $this->command->info("Syncing Customer Transaction Totals JSON...");
        $customers = Customer::where('is_active', true)->get();
        $bar = $this->command->getOutput()->createProgressBar(count($customers));
        $bar->start();

        foreach ($customers as $customer) {
            SyncVendTransactionTotalsJson::dispatchSync($customer);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Done!");
    }
}
