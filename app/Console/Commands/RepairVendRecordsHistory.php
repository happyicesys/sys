<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Vend;
use App\Models\VendRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairVendRecordsHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend-records:repair-history {--vend_id= : specific vend_id to repair}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repairs historical vend_records customer binding based on customer_movement_history_json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting VendRecords history repair...");

        $query = Vend::whereNotNull('customer_movement_history_json');

        if ($this->option('vend_id')) {
            $query->where('id', $this->option('vend_id'));
        }

        $vends = $query->get(['id', 'code', 'customer_movement_history_json']);
        $totalVends = $vends->count();

        $this->info("Found {$totalVends} vends with movement history.");
        $bar = $this->output->createProgressBar($totalVends);

        $updatedRecordsCount = 0;

        foreach ($vends as $vend) {
            $history = $vend->customer_movement_history_json;

            if (empty($history) || !is_array($history)) {
                $bar->advance();
                continue;
            }

            // Sort history by date ASC
            usort($history, function ($a, $b) {
                return strcmp($a['created_at'], $b['created_at']);
            });

            // Build timeline of bindings
            $timeline = [];
            $currentBinding = null;
            $lastDate = null;

            foreach ($history as $entry) {
                $date = Carbon::parse($entry['created_at']);
                // Logic based on previous observation:
                // An entry represents an event.
                // If it's a binding event, we start a binding period.
                // If it's an unbinding event, we end the previous period (if any).
                // "is_binding": true/false/null

                $isBinding = isset($entry['is_binding']) ? $entry['is_binding'] : true;

                if ($currentBinding) {
                    // Close previous segment
                    $timeline[] = [
                        'start' => $lastDate,
                        'end' => $date, // Exclusive
                        'customer_id' => $currentBinding['customer_id']
                    ];
                }

                if ($isBinding) {
                    // Start new segment
                    $currentBinding = ['customer_id' => $entry['id']];
                    $lastDate = $date;
                } else {
                    // Unbind
                    $currentBinding = null;
                    $lastDate = $date;
                }
            }

            // Add final open-ended segment if currently bound
            if ($currentBinding) {
                $timeline[] = [
                    'start' => $lastDate,
                    'end' => Carbon::now()->addYear(), // Future
                    'customer_id' => $currentBinding['customer_id']
                ];
            }

            if (empty($timeline)) {
                $bar->advance();
                continue;
            }

            // Fetch records for this vend
            // We only care about records that fall within or after the timeline start
            $firstDate = $timeline[0]['start'];

            $records = VendRecord::where('vend_id', $vend->id)
                ->where('date', '>=', $firstDate)
                ->get();

            // Cache for customer operator lookups to avoid N+1
            $customerOperators = [];

            foreach ($records as $record) {
                $recordDate = Carbon::parse($record->date);

                // Find matching timeline segment
                $activeCustomerId = null;
                foreach ($timeline as $segment) {
                    if ($recordDate->gte($segment['start']) && $recordDate->lt($segment['end'])) {
                        $activeCustomerId = $segment['customer_id'];
                        break;
                    }
                }

                // If activeCustomerId is null, it means UNBOUND
                // If record has customer_id, we need to NULL it.
                // If record has different customer_id, update it.

                if ($record->customer_id != $activeCustomerId) {
                    $record->customer_id = $activeCustomerId;

                    if ($activeCustomerId) {
                        // Find Operator for new customer
                        if (!isset($customerOperators[$activeCustomerId])) {
                            $customer = Customer::find($activeCustomerId);
                            $customerOperators[$activeCustomerId] = $customer ? $customer->operator_id : null;
                        }
                        if ($customerOperators[$activeCustomerId]) {
                            $record->operator_id = $customerOperators[$activeCustomerId];
                        }
                    } else {
                        // If unbinding, what about operator_id?
                        // Dashboard filters usually ignore operator if customer_id is null contextually for "Active" graph.
                        // But let's leave operator_id as is or null?
                        // Ideally, unbinded machine still belongs to an operator (the owner).
                        // But vend_records.operator_id is usually the customer's operator.
                        // Let's NOT touch operator_id if unbinding, just NULL the customer_id.
                        // Wait, if we keep operator_id, it might still show up in operator filter.
                        // But dashboard query `whereNotNull('customer_id')` will hide it anyway.
                    }

                    $record->save();
                    $updatedRecordsCount++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Repair complete. Updated {$updatedRecordsCount} vend_records.");
    }
}
