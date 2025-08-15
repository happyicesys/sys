<?php

namespace Database\Seeders;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillCustomerVendBindingsSeeder extends Seeder
{
    public function run(): void
    {
        $totalInserted = 0;

        // Adjust table name if yours is different
        $table = 'customer_vend_bindings';

        // Optional: wrap in a transaction chunk-by-chunk for safety
        Vend::query()
            ->select(['id', 'customer_movement_history_json'])
            ->chunkById(500, function ($vends) use (&$totalInserted, $table) {

                $rows = [];

                foreach ($vends as $vend) {
                    // History can be cast to array on the model or raw JSON string
                    $history = $vend->customer_movement_history_json;

                    if (is_string($history)) {
                        $decoded = json_decode($history, true);
                    } else {
                        $decoded = $history; // already array or null
                    }

                    if (!is_array($decoded) || empty($decoded)) {
                        continue; // nothing to backfill for this vend
                    }

                    foreach ($decoded as $item) {
                        // Skip malformed entries
                        if (!is_array($item) || !isset($item['id'])) {
                            continue;
                        }

                        $createdAt = null;
                        if (!empty($item['created_at'])) {
                            try {
                                $createdAt = Carbon::parse($item['created_at']);
                            } catch (\Throwable $e) {
                                $createdAt = null;
                            }
                        }

                        $rows[] = [
                            'vend_id'     => $vend->id,
                            'customer_id' => (int) $item['id'],                 // your “above id = customer_id”
                            'user_id'     => $item['created_by'] ?? null,
                            'is_binding'  => (bool) ($item['is_binding'] ?? false),
                            'created_at'  => $createdAt?->toDateTimeString() ?? now(),
                            'updated_at'  => $createdAt?->toDateTimeString() ?? now(),
                        ];
                    }
                }

                if (!empty($rows)) {
                    // If you expect duplicates, switch to upsert with a suitable unique key
                    // Example unique key suggestion: ['vend_id', 'customer_id', 'created_at']
                    // DB::table($table)->upsert($rows, ['vend_id','customer_id','created_at'], ['is_binding','user_id','updated_at']);

                    DB::table($table)->insert($rows);
                    $totalInserted += count($rows);
                }
            });

        $this->command?->info("Backfill complete. Inserted ~{$totalInserted} rows into {$table}.");
    }
}
