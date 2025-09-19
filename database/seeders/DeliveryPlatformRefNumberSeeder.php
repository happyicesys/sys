<?php

namespace Database\Seeders;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryPlatformRefNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Group all mappings by platform_ref_id so we can set the
        // DeliveryPlatformRefNumber created_at as the OLDEST mapping's created_at
        $grouped = DeliveryProductMappingVend::with('deliveryProductMapping')
            ->whereNotNull('platform_ref_id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('platform_ref_id');

        foreach ($grouped as $refNumberValue => $items) {
            // Oldest mapping in this group
            $oldest = $items->sortBy('created_at')->first();

            // Create or fetch the ref number
            $refNumber = DeliveryPlatformRefNumber::firstOrNew([
                'ref_number' => $refNumberValue,
            ]);

            // Always ensure these fields are set/updated
            $refNumber->delivery_platform_id = $refNumber->delivery_platform_id ?? 1; // default platform id
            $refNumber->operator_id = $oldest->deliveryProductMapping->operator_id;
            $refNumber->status = $oldest->is_active ? DeliveryPlatformRefNumber::STATUS_ACTIVE : DeliveryPlatformRefNumber::STATUS_INACTIVE;

            // If it's new or created_at is later than oldest, set to oldest
            if (!$refNumber->exists || ($refNumber->created_at && $refNumber->created_at->gt($oldest->created_at))) {
                $refNumber->created_at = $oldest->created_at;
            }

            // Persist without disturbing timestamps beyond what we set
            $refNumber->saveQuietly();

            // Bind all mappings to this ref number
            foreach ($items as $mapping) {
                $mapping->delivery_platform_ref_number_id = $refNumber->id;
                $mapping->saveQuietly();
            }
        }
    }
}
