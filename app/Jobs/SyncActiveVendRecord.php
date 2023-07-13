<?php

namespace App\Jobs;

use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncActiveVendRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dateFrom;
    protected $dateTo;
    /**
     * Create a new job instance.
     */
    public function __construct($dateFrom, $dateTo)
    {
        $this->dateFrom = $dateFrom ?? Carbon::today()->toDateString();
        $this->dateTo = $dateTo ?? Carbon::today()->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startDate = Carbon::parse($this->dateFrom)->startOfDay();
        $endDate = Carbon::parse($this->dateTo)->endOfDay();

        $diffInDays = $startDate->diffInDays($endDate);

        for($i = 0; $i <= $diffInDays; $i++) {
            $date = $startDate->copy()->addDays($i);

            $activeVends = Vend::query()
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
            ->whereIn('vends.id', function($query) use ($date) {
                $query->select('vend_id')
                    ->from('vend_bindings')
                    ->where('is_active', true)
                    ->whereNull('termination_date')
                    ->whereDate('begin_date', '<=', $date);
            })
            ->select('*', 'vends.id');

        $inactiveVends = Vend::query()
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->whereIn('vends.id', function($query) use ($date) {
                $query->select('vend_id')
                    ->from('vend_bindings')
                    ->where('is_active', false)
                    ->whereNotNull('termination_date')
                    ->whereDate('begin_date', '<=', $date)
                    ->whereDate('termination_date', '>=', $date);
            })
            ->select('*', 'vends.id');


        $vends = $activeVends->union($inactiveVends)
            ->orderBy('code')
            ->get();

            foreach($vends as $vend) {
                VendRecord::updateOrCreate([
                    'vend_id' => $vend->id,
                    'date' => $date->copy()->toDateString(),
                ],
                [
                    'customer_id' => $vend->customer_id,
                    'day' => $date->copy()->day,
                    'month' => $date->copy()->month,
                    'monthname' => $date->copy()->format('F'),
                    'operator_id' => $vend->operator_id,
                    'year' => $date->copy()->year,
                    'vend_code' => $vend->code,
                ]);
            }
        }


    }
}
