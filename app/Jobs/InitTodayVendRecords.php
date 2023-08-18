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
                        // ->where('is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->select(
                'vends.id as id',
                'vends.code as code',
                'operator_vend.operator_id as operator_id',
                'vend_bindings.customer_id as customer_id'
            )
            ->where('vends.is_active', true)
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

        $unbindVends = Vend::query()
        ->leftJoin('operator_vend', function($query) {
            $query->on('operator_vend.vend_id', '=', 'vends.id')
                    ->latest('operator_vend.created_at')
                    ->limit(1);
        })
        ->doesntHave('vendBindings')
        ->has('operators')
        ->select(
            'vends.id as id',
            'vends.code as code',
            'operator_vend.operator_id as operator_id'
        )
        ->whereNull('vends.termination_date')
        ->where('vends.last_updated_at', '>=', Carbon::now()->subDays(3)->startOfDay())
        ->get();

        foreach($unbindVends as $unbindVend) {
            VendRecord::updateOrCreate([
                'vend_id' => $unbindVend->id,
                'date' => Carbon::yesterday()->toDateString(),
            ],
            [
                'day' => Carbon::yesterday()->day,
                'month' => Carbon::yesterday()->month,
                'monthname' => Carbon::yesterday()->format('F'),
                'operator_id' => $unbindVend->operator_id,
                'year' => Carbon::yesterday()->year,
                'vend_code' => $unbindVend->code,
            ]);
        }
    }
}
