<?php

namespace Database\Seeders;

use App\Models\DeliveryPlatformOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryPlatformDispensingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryPlatformOrder::where('status', DeliveryPlatformOrder::STATUS_COLLECTED)
            ->update([
                'status' => DeliveryPlatformOrder::STATUS_DELIVERED,
            ]);
    }
}
