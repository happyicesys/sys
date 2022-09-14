<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\Profile;
use App\Models\Status;
use App\Models\Tax;
use App\Models\Zone;
use App\Traits\SearchAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\PhoneNumber;

class SyncCustomerApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customers-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve customers json from admin happyice vend-code';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public $endPointUrl = 'https://admin.happyice.com.sg/api/person/vend-code';

    public function handle()
    {
        $response = Http::get($this->endPointUrl);

        $customersCollection = $response->collect();
        $profileAddressDefaultResult = $this->getAddressResult(659526);
        $className = get_class(new Customer());
        $bankId = null;
        $baseCurrencyCountryId = null;
        $categoryId = null;
        $categoryGroupId = null;
        $payTermId = null;
        $profileId = null;
        $zoneId = null;

        if($customersCollection) {
            foreach($customersCollection as $customerCollection) {
                switch($statusData = $customerCollection['active']) {
                    case 'Yes':
                        $status = Status::updateOrCreate([
                            'name' => 'Active',
                            'classname' => $className,
                        ]);
                        break;
                    case 'No':
                        $status = Status::updateOrCreate([
                            'name' => 'Inactive',
                            'classname' => $className,
                        ]);
                        break;
                    case 'New':
                    case 'Pending':
                        $status = Status::updateOrCreate([
                            'name' => $statusData,
                            'classname' => $className,
                        ]);
                        break;
                }

                if($bankData = $customerCollection['bank']) {
                    $bank = Bank::updateOrCreate([
                        'name' => $bankData['name']
                    ]);
                    $bankId = $bank->id;
                }

                if($categoryData = $customerCollection['custcategory']) {
                    if($categoryGroupData = $categoryData['custcategory_group']) {
                        $categoryGroup = CategoryGroup::updateOrCreate([
                            'name' => $categoryGroupData['name'],
                        ], [
                            'desc' => $categoryGroupData['desc'],
                        ]);
                        $categoryGroupId = $categoryGroup->id;
                    }

                    $category = Category::updateOrCreate([
                        'name' => $categoryData['name'],
                        'classname' => $className,
                    ], [
                        'desc' => $categoryData['desc'],
                        'category_group_id' => $categoryGroup ? $categoryGroup->id : null,
                        'remarks' => $categoryData['map_icon_file'],
                    ]);
                    $categoryId = $category->id;
                }

                if($paymentTermData = $customerCollection['payterm']) {
                    $paymentTerm = PaymentTerm::updateOrCreate([
                        'name' => $paymentTermData
                    ]);
                    $paymentTermId = $paymentTerm->id;
                }

                if($profileData = $customerCollection['profile']) {
                    $baseCurrencyCountryData = $profileData['currency'];
                    $baseCurrencyCountry = null;
                    switch($baseCurrencyCountryData['currency_name']) {
                        case 'SGD':
                            $baseCurrencyCountry = Country::updateOrCreate([
                                'currency_name' => $baseCurrencyCountryData['currency_name'],
                            ], [
                                'name' => 'Singapore',
                                'code' => 'SG',
                                'currency_symbol' => 'S$',
                                'phone_code' => '65',
                                'is_state' => false,
                                'sequence' => 1,
                            ]);
                            $baseCurrencyCountryId = $baseCurrencyCountry->id;
                            break;
                        case 'MYR':
                            $baseCurrencyCountry = Country::updateOrCreate([
                                'currency_name' => $baseCurrencyCountryData['currency_name'],
                            ], [
                                'name' => 'Malaysia',
                                'code' => 'MY',
                                'currency_symbol' => 'RM',
                                'phone_code' => '60',
                                'is_state' => true,
                                'sequence' => 2,
                            ]);
                            $baseCurrencyCountryId = $baseCurrencyCountry->id;
                            break;
                        case 'RMB':
                            $baseCurrencyCountry = Country::updateOrCreate([
                                'currency_name' => $baseCurrencyCountryData['currency_name'],
                            ], [
                                'name' => 'China',
                                'code' => 'CN',
                                'currency_symbol' => '¥',
                                'phone_code' => '86',
                                'is_state' => true,
                                'sequence' => 3,
                            ]);
                            $baseCurrencyCountryId = $baseCurrencyCountry->id;
                            break;
                    }
                    $profile = Profile::updateOrCreate([
                        'name' => $profileData['name']
                    ], [
                        'alias' => $profileData['acronym'],
                        'uen' => $profileData['roc_no'],
                        'base_currency_id' => $baseCurrencyCountryId,
                    ]);
                    $profileId = $profile->id;

                    $profile->address()->updateOrCreate([
                        'postcode' => 659526
                    ], [
                        'unit_num' => '01-198',
                        'block_num' => $profileAddressDefaultResult['block_num'],
                        'street_name' => $profileAddressDefaultResult['street_name'],
                        'building' => $profileAddressDefaultResult['building'],
                        'full_address' => $profileAddressDefaultResult['full_address'],
                        'postcode' => $profileAddressDefaultResult['postcode'],
                        'latitude' => $profileAddressDefaultResult['latitude'],
                        'longitude' => $profileAddressDefaultResult['longitude'],
                        'country_id' => $baseCurrencyCountryId,
                    ]);

                    if($profileData['contact']) {
                        $profile->contact()->updateOrCreate([
                            'phone_num' => PhoneNumber::make(str_replace(' ', '', $profileData['contact']), $baseCurrencyCountry->code)->formatForCountry($baseCurrencyCountry->code),
                            'phone_country_id' => $baseCurrencyCountry->id,
                        ], [
                            'name' => $profileData['attn'],
                        ]);
                    }

                    if($profileData['gst']) {
                        $tax = Tax::firstOrFail();
                        $profile->profileTaxes()->updateOrCreate([
                            'tax_id' => $tax->id,
                        ], [
                            'is_inclusive' => $profileData['is_gst_inclusive'],
                        ]);
                    }
                }

                if($zoneData = $customerCollection['zone']) {
                    $zone = Zone::updateOrCreate([
                        'name' => $zoneData['name'],
                    ], [
                        'sequence' => $zoneData['priority'],
                    ]);
                    $zoneId = $zone->id;
                }

            }
        }
    }
}
