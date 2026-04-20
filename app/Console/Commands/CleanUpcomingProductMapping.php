<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanUpcomingProductMapping extends Command
{
    protected $signature = 'product-mapping:clean-upcoming';

    protected $description = 'Null out upcoming_product_mapping_id that is self-referencing or points to N/A';

    public function handle()
    {
        $this->call('db:seed', [
            '--class' => 'UnbindProductMappingSelfReferenceSeeder',
            '--force' => true,
        ]);
    }
}
