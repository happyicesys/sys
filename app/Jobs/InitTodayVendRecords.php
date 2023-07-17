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
            ->has('latestVendBinding')
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->select('*', 'vends.id as id', 'vends.code as code')
            ->get();

        foreach($vends as $vend) {
            VendRecord::updateOrCreate([
                'vend_id' => $vend->id,
                'date' => Carbon::yesterday()->toDateString(),
            ], [
                'customer_id' => $vend->customer_id,
                'day' => Carbon::yesterday()->day,
                'month' => Carbon::yesterday()->month,
                'monthname' => Carbon::yesterday()->format('F'),
                'operator_id' => $vend->operator_id,
                'year' => Carbon::yesterday()->year,
                'vend_code' => $vend->code,
            ]);
        }
    }
}
