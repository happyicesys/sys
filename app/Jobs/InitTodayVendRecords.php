<?php

namespace App\Jobs;

use App\Models\Vend;
use App\Models\VendRecord;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitTodayVendRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vends = Vend::query()
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->select(
                'vends.id as id',
                'vends.code as code',
                'vends.operator_id',
                'vends.vend_prefix_id',
                'location_types.id as location_type_id',
                'customers.id as customer_id'
            )
            ->where('customers.is_active', true)
            ->get();

        foreach($vends as $vend) {
            VendRecord::updateOrCreate([
                'vend_id' => $vend->id,
                'date' => Carbon::yesterday()->toDateString(),
            ], [
                'customer_id' => $vend->customer_id,
                'day' => Carbon::yesterday()->day,
                'location_type_id' => $vend->location_type_id,
                'month' => Carbon::yesterday()->month,
                'monthname' => Carbon::yesterday()->format('F'),
                'operator_id' => $vend->operator_id,
                'year' => Carbon::yesterday()->year,
                'vend_code' => $vend->code,
                'vend_prefix_id' => $vend->vend_prefix_id,
            ]);
        }
    }
}
