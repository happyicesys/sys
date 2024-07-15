<?php

namespace Database\Seeders;

use App\Models\Vend;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncVendTransactionsProduct extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vend = Vend::query()
        ->with([
            'productMapping.productMappingItems.product',
            'vendTransactions'
        ])
        ->where('code', 10010)
        ->first();

        if($vend and $vend->product_mapping_id) {
            foreach($vend->vendTransactions as $vendTransaction) {

            }
        }
    }
}
