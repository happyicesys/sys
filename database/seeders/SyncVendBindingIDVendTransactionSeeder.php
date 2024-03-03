<?php

namespace Database\Seeders;

use App\Models\VendBinding;
use App\Models\VendTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncVendBindingIDVendTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendTransactions = VendTransaction::all();

        VendTransaction::chunk(500, function ($vendTransactions) {
            foreach ($vendTransactions as $vendTransaction) {
                $vendBinding = VendBinding::where('customer_id', $vendTransaction->customer_json['id'])
                    ->where('vend_id', $vendTransaction->vend_json['id'])
                    ->orderBy('begin_date', 'desc')
                    ->first();

                if ($vendBinding) {
                    $vendTransaction->vend_binding_id = $vendBinding->id;
                    $vendTransaction->save();
                }
            }
        });
    }
}
