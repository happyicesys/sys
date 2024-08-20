<?php

namespace Database\Seeders;

use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FirstOpsJobItems extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $opsJob = OpsJob::create([
            'code' => '10001',
            'created_by' => 1,
            'date' => Carbon::today()->subDay()->toDateString(),
            'delivered_by' => 1,
            'operator_id' => 1,
            'updated_by' => null,
            'updated_at' => null,
        ]);

        $vends = Vend::query()
            ->with('vendChannels')
            ->where('is_active', true)
            // ->where('is_testing', false)
            ->has('customer')
            ->orderBy('code', 'asc')
            ->get();

        foreach($vends as $vend) {
            $opsJobItem = OpsJobItem::create([
                'customer_id' => $vend->customer_id,
                'ops_job_id' => $opsJob->id,
                'vend_id' => $vend->id,
                'cash_amount' => 0,
                'cashless_amount' => 0,
                'status' => 3,
                'updated_by' => 1,
                'picked_at' => Carbon::today()->subDay()->endOfDay(),
                'picked_by' => 1,
                'completed_at' => Carbon::today()->subDay()->endOfDay(),
                'completed_by' => 1,
                'acc_total_amount' => 0,
                'acc_total_cash_amount' => 0,
                'acc_total_cashless_amount' => 0,
                'acc_total_count' => 0,
                'acc_total_promo_amount' => 0,
                'cash_amount' => 0,
                'cashless_amount' => 0,
            ]);

            foreach($vend->vendChannels as $vendChannel) {
                $opsJobItem->opsJobItemChannels()->create([
                    'ops_job_id' => $opsJob->id,
                    'product_id' => $vendChannel->product_id ?? 0,
                    'vend_channel_code' => $vendChannel->code,
                    'vend_channel_id' => $vendChannel->id,
                    'vend_code' => $vend->code,
                    'actual_qty' => $vendChannel->qty,
                    'capacity' => $vendChannel->capacity,
                    'picked_qty' => $vendChannel->qty,
                    'qty' => $vendChannel->qty,
                ]);
            }
        }
    }
}
