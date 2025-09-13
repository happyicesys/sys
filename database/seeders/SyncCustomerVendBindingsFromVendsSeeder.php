<?php

// database/seeders/SyncCustomerVendBindingsFromVendsSeeder.php
namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncCustomerVendBindingsFromVendsSeeder extends Seeder
{
    /** Tune if needed */
    private int $chunkSize = 500;

    public function run(): void
    {
        // Process only rows where JSON looks non-empty
        DB::table('vends')
            ->select(['id as vend_id', 'customer_movement_history_json'])
            ->whereNotNull('customer_movement_history_json')
            ->whereRaw("TRIM(customer_movement_history_json) <> ''")
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($vends) {
                $batch = [];

                foreach ($vends as $vend) {
                    $raw = $vend->customer_movement_history_json;

                    // Skip obvious empties like '[]'
                    $trimmed = trim((string) $raw);
                    if ($trimmed === '' || $trimmed === '[]' || Str::startsWith($trimmed, ['[null', 'null'])) {
                        continue;
                    }

                    // Parse JSON safely
                    $events = json_decode($raw, true);
                    if (!is_array($events)) {
                        // bad JSON, skip
                        continue;
                    }

                    foreach ($events as $evt) {
                        // Defensive extraction
                        $customerId  = $evt['id']            ?? null;
                        $createdAt   = $evt['created_at']     ?? null;
                        $userId      = $evt['created_by']     ?? null;
                        $isBinding   = $evt['is_binding']     ?? null;

                        if (!$customerId || !$createdAt || $isBinding === null) {
                            // minimal required fields missing; skip this item
                            continue;
                        }

                        // Normalize timestamp; if parse fails, skip
                        try {
                            $ts = CarbonImmutable::parse($createdAt)->toDateTimeString();
                        } catch (\Throwable $e) {
                            continue;
                        }

                        // Coerce truthy/falsey to 0/1
                        $isBindingTiny = (int) filter_var($isBinding, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                        if ($isBindingTiny === null) {
                            // If it's 0/1 string like "0"/"1", cast
                            $isBindingTiny = (int) ((string)$isBinding === '1');
                        }

                        $batch[] = [
                            'customer_id' => (int) $customerId,
                            'vend_id'     => (int) $vend->vend_id,
                            'user_id'     => $userId ? (int) $userId : null,
                            'is_binding'  => $isBindingTiny,
                            'created_at'  => $ts,
                            'updated_at'  => $ts, // keep same as source moment
                        ];
                    }
                }

                if (!empty($batch)) {
                    // Use upsert to avoid duplicates; requires the unique index above
                    DB::table('customer_vend_bindings')->upsert(
                        $batch,
                        ['vend_id', 'customer_id', 'is_binding', 'created_at'], // conflict target
                        // Nothing to update on conflict; we keep the original row intact.
                        ['updated_at'] // touch updated_at only if you prefer; or pass [] to no-op
                    );
                }
            });
    }
}
