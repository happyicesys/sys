<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateVendRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend-records:remove-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes duplicate vend_records entries (same vend_id on same date), keeping the latest updated one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Scanning for duplicate vend_records...");

        // Find duplicates
        // Use a subquery or raw query for efficiency on large table
        // We want (vend_id, date) pairs with count > 1

        $duplicates = DB::table('vend_records')
            ->select('vend_id', 'date', DB::raw('COUNT(*) as count'))
            ->groupBy('vend_id', 'date')
            ->having('count', '>', 1)
            ->get();

        $totalGroups = $duplicates->count();
        $this->info("Found {$totalGroups} groups of duplicates.");

        if ($totalGroups == 0) {
            $this->info("No duplicates found.");
            return;
        }

        $bar = $this->output->createProgressBar($totalGroups);
        $deletedCount = 0;

        foreach ($duplicates as $group) {
            // Get all records for this group
            $records = DB::table('vend_records')
                ->where('vend_id', $group->vend_id)
                ->where('date', $group->date)
                ->get();

            // Prioritize logic:
            // 1. Higher total_amount
            // 2. Higher total_count
            // 3. Has customer_id (not null preferred)
            // 4. Latest updated_at

            $sorted = $records->sort(function($a, $b) {
                // 1. Total Amount
                if ($a->total_amount != $b->total_amount) {
                    return $b->total_amount <=> $a->total_amount; // DESC
                }

                // 2. Total Count
                if ($a->total_count != $b->total_count) {
                    return $b->total_count <=> $a->total_count; // DESC
                }

                // 3. Customer ID (NotNull preferred)
                $aHasCust = !is_null($a->customer_id);
                $bHasCust = !is_null($b->customer_id);
                if ($aHasCust != $bHasCust) {
                    return $bHasCust <=> $aHasCust; // true > false (DESC)
                }

                // 4. Latest Update
                return $b->updated_at <=> $a->updated_at; // DESC
            });

            // Keep top one (the "best" one)
            $keeper = $sorted->first();

            // Delete others
            $deleteIds = $sorted->pluck('id')->filter(function($id) use ($keeper) {
                return $id != $keeper->id;
            })->toArray();

            if (!empty($deleteIds)) {
                DB::table('vend_records')->whereIn('id', $deleteIds)->delete();
                $deletedCount += count($deleteIds);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Cleanup complete. Removed {$deletedCount} duplicate vend_records.");
    }
}
