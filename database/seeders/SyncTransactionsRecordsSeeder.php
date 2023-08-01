<?php

namespace Database\Seeders;

use App\Jobs\StoreVendsRecord;
use App\Models\Vend;
use App\Models\VendTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncTransactionsRecordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
