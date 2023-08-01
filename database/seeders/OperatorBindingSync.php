<?php

namespace Database\Seeders;

use App\Jobs\StoreVendsRecord;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OperatorBindingSync extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $vendRecords = VendRecord::query()
            ->where('day', 30)
            ->where('month', 7)
            ->where('year', 2023)
            ->get();
        foreach($vendRecords as $vendRecord) {
            $vendRecord->vend->operators()->syncWithoutDetaching($vendRecord->operator_id);
        }

        // $vendTransactions = VendTransaction::query()
        //     ->where('created_at', '>=', Carbon::parse('2023-07-31')->startOfDay())
        //     ->where('created_at', '<=', Carbon::parse('2023-08-02')->endOfDay())
        //     ->get();
        $vendTransactions = VendTransaction::query()
            ->where('created_at', '>=', '2023-07-31 00:00:00')
            ->where('created_at', '<=', '2023-08-02 23:59:59')
            ->select('id', 'vend_id')
            ->get();

        foreach($vendTransactions as $vendTransaction) {

            $vendTransaction->operator_id = Vend::findOrFail($vendTransaction->vend_id)->operators->first()?->id;
            $vendTransaction->save();
        }
        StoreVendsRecord::dispatch('2023-07-31', '2023-08-01');
    }
}
