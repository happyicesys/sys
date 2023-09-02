<?php

namespace Database\Seeders;

use App\Models\TempVendTransaction;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConvertTempVendTransactionDatetime extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tempVendTransactions = TempVendTransaction::all();

        foreach ($tempVendTransactions as $tempVendTransaction) {
            $tempVendTransaction->transaction_datetime = Carbon::parse($tempVendTransaction->transaction_datetime_ref);
            $tempVendTransaction->save();
        }
    }
}
