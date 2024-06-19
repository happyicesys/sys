<?php

namespace Database\Seeders;

use App\Models\VendConfig;
use App\Models\VendConfigPrefixBinding;
use App\Models\VendPrefix;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendConfigPrefixManyToMany extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendPrefixes = VendPrefix::all();

        foreach($vendPrefixes as $vendPrefix) {
            if($vendPrefix->vend_config_id === null) {
                continue;
            }

            $vendPrefix->vendConfigs()->attach($vendPrefix->vend_config_id);
        }
    }
}
