<?php

namespace Database\Seeders;

use App\Models\TempVendTransaction;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Jobs\Vend\CreateVendTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncTempVendTransaction extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tempVendTransactions = TempVendTransaction::all();
        foreach($tempVendTransactions as $tempVendTransaction) {
            $isExistTransaction = VendTransaction::query()
                ->leftJoin('vends', 'vends.id', '=', 'vend_transactions.vend_id')
                ->where('vends.code', $tempVendTransaction->vend_code)
                ->where('transaction_datetime', '>=', $tempVendTransaction->transaction_datetime->subMinutes(1))
                ->where('transaction_datetime', '<=', $tempVendTransaction->transaction_datetime->addMinutes(1))
                ->where('order_id', 'LIKE', '2%')
                ->first();

            if($isExistTransaction) {
                continue;
            }

            // {
            //     "SId": 32,
            //     "TId": 61,
            //     "Vid": 2671,
            //     "ISOK": 1,
            //     "SErr": 0,
            //     "TIME": "2023-08-29 00:00:34",
            //     "Type": "TRADE",
            //     "BILLS": 0,
            //     "COINS": 0,
            //     "Price": 150,
            //     "ORDRID": "2023082900003400061",
            //     "CHANEGS": 0,
            //     "PAY_TYPE": 1
            // }
            $paymentMethodCode = 0;
            switch($tempVendTransaction->ref_payment_method_id) {
                case 1:
                    $paymentMethodCode = 1;
                    break;
                case 3:
                    $paymentMethodCode = 0;
                    break;
                default:
                    $paymentMethodCode = 0;
            }
            $inputArr = [
                'SId' => $tempVendTransaction->channel_code,
                'Vid' => $tempVendTransaction->vend_code,
                'ISOK' => 1,
                'SErr' => 0,
                'TIME' => $tempVendTransaction->transaction_datetime->toDateTimeString(),
                'Type' => 'TRADE',
                'BILLS' => 0,
                'COINS' => 0,
                'Price' => $tempVendTransaction->amount,
                'ORDRID' => $tempVendTransaction->ref_id,
                'CHANEGS' => 0,
                'PAY_TYPE' => $paymentMethodCode,
            ];

            $vend = Vend::where('code', $tempVendTransaction->vend_code)->first();

            CreateVendTransaction::dispatch($inputArr, $vend, false)->onQueue('default');

        }
    }
}
