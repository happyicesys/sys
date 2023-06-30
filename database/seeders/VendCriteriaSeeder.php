<?php

namespace Database\Seeders;

use App\Models\LocationType;
use App\Models\VendCriteria;
use App\Models\Holiday;
use App\Models\Vend;
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
            'name' => 'Sales Frequency',
            'code' => 'sales_frequency',
            'sequence' => 1,
            'value' =>  48,
            'options_json' => [
                '24' => 'Last 24 hours',
                '48' => 'Last 48 hours',
            ],
        ]);

        VendCriteria::create([
            'classname' => get_class(new Holiday()),
            'has_sub_criteria' => true,
            'name' => 'Holidays',
            'code' => 'holiday',
            'sequence' => 2,
            'value' =>  'all',
            'options_json' => [
                'all' => 'Every Weekends and Holidays',
                'weekend' => 'Every Weekends',
                'holiday' => 'Every Holidays',
            ],
        ]);

        VendCriteria::create([
            'classname' => get_class(new VendChannelError()),
            'has_sub_criteria' => true,
            'name' => 'Channel Errors',
            'code' => 'channel_error',
            'sequence' => 3,
            'value' =>  'in',
            'value2' => '9',
            'options_json' => [
                'all' => 'Any Error (Same Weightage)',
                'weighted' => 'Weighted Error (Based on Configured Weightage)',
                'in' => 'Only the Error(s) Stated ("," to add more)',
            ],
        ]);

        VendCriteria::create([
            'classname' => get_class(new Vend()),
            'has_sub_criteria' => false,
            'name' => 'Remaining SKU',
            'code' => 'remaining_sku_percentage',
            'sequence' => 4,
            'value' =>  25,
            'options_json' => [
                'lte' => 'Percentage Lower Than',
            ],
        ]);

        VendCriteria::create([
            'classname' => get_class(new Vend()),
            'has_sub_criteria' => false,
            'name' => 'Balance Stock',
            'code' => 'balance_stock_percentage',
            'sequence' => 5,
            'value' =>  15,
            'options_json' => [
                'lte' => 'Percentage Lower Than',
            ],
        ]);

        VendCriteria::create([
            'classname' => get_class(new Vend()),
            'has_sub_criteria' => false,
            'name' => 'Last Visit',
            'code' => 'last_visit',
            'sequence' => 6,
            'value' =>  'gte_days',
            'value2' => 7,
            'options_json' => [
                'lte_days' => 'More Than Day(s)',
            ],
        ]);

        VendCriteria::create([
            'classname' => get_class(new LocationType()),
            'has_sub_criteria' => true,
            'name' => 'Location Type',
            'code' => 'location_type',
            'sequence' => 7,
            'value' =>  'weighted',
            'options_json' => [
                'weighted' => 'Weigted Location (Based on Configured Weightage)',
            ],
        ]);
    }
}
