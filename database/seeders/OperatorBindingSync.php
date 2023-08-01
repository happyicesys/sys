<?php

namespace Database\Seeders;

use App\Models\VendRecord;
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

    }
}
