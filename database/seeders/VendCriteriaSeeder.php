<?php

namespace Database\Seeders;

use App\Models\VendCriteria;
use App\Models\Holiday;
use App\Models\VendChannelError;
use App\Models\VendSubCriteria;
use App\Models\VendTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendCriteriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VendCriteria::create([
            'classname' => get_class(new VendTransaction()),
            'has_sub_criteria' => false,
            'operator' => '<=',
            'name' => 'Sales Frequency',
            'sequence' => 1,
            'value' =>  48,
            'options_json' => [
                '24' => 'Last 24 hours',
                '48' => 'Last 48 hours',
            ];
        ]);

        VendCriteria::create([
            'classname' => get_class(new Holiday()),
            'has_sub_criteria' => true,
            'operator' => '=',
            'name' => 'Holidays',
            'sequence' => 2,
        ]);

        VendCriteria::create([
            'classname' => get_class(new VendChannelError()),
            'has_sub_criteria' => true,
            'operator' => '=',
            'name' => 'Channel Errors',
            'sequence' => 3,
        ]);

        VendCriteria::create([
            'classname' => get_class(new VendChannelError()),
            'has_sub_criteria' => false,
            'operator' => '=',
            'name' => 'Remaining SKU',
            'sequence' => 4,
        ]);
    }
}
