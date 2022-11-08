<?php

namespace App\Jobs;

use App\Models\Bank;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\Profile;
use App\Models\Status;
use App\Models\Tax;
use App\Models\Vend;
use App\Models\Zone;
use App\Traits\SearchAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\PhoneNumber;


class ProcessCustomerData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SearchAddress, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $dataArr;
    protected $endPointUrl;

    public function __construct($dataArr, $endPointUrl)
    {
        $this->dataArr = $dataArr;
        $this->endPointUrl = $endPointUrl;
    }

    public function handle()
    {
        if($this->dataArr) {
            $customersCollection = collect([$this->dataArr]);
        }else {
            $response = Http::get($this->endPointUrl);
            $customersCollection = $response->collect();
        }

        $profileAddressDefaultResult = $this->getAddressResult(659526);
        $className = get_class(new Customer());
        $bankId = null;
        $baseCurrencyCountryId = null;
        $categoryId = null;
        $categoryGroupId = null;
        $payTermId = null;
        $profileId = null;
        $statusId = null;
        $zoneId = null;

        if($customersCollection) {
            foreach($customersCollection as $customerCollection) {
                switch($statusData = $customerCollection['active']) {
                    case 'Yes':
                        $status = Status::updateOrCreate([
                            'name' => 'Active',
                            'classname' => $className,
                        ]);
                        $statusId = $status->id;
                        break;
                    case 'No':
                        $status = Status::updateOrCreate([
                            'name' => 'Inactive',
                            'classname' => $className,
                        ]);
                        $statusId = $status->id;
                        break;
                    case 'New':
                    case 'Pending':
                        $status = Status::updateOrCreate([
                            'name' => $statusData,
                            'classname' => $className,
                        ]);
                        $statusId = $status->id;
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
                            'classname' => $className,
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

                $customer = Customer::updateOrCreate([
                    'code' => $customerCollection['cust_id'],
                ], [
                    'name' => $customerCollection['company'],
                    'profile_id' => $profileId,
                    'status_id' => $statusId,
                    'bank_id' => $bankId,
                    'bank_remarks' => $customerCollection['account_number'],
                    'category_id' => $categoryId,
                    'payment_term_id' => $paymentTermId,
                    'zone_id' => $zoneId,
                    'remarks' => $customerCollection['remark'],
                    'ops_note' => $customerCollection['operation_note'],
                    'created_at' => $customerCollection['created_at'],
                ]);

                if($customerCollection['is_dvm'] or $customerCollection['is_vending'] or $customerCollection['is_combi']) {
                    $vend = Vend::where('code', $customerCollection['vend_code'])->first();

                    if($vend) {
                        $customer->vendBinding()->updateOrCreate([
                            'vend_id' => $vend->id,
                            ],[
                            'begin_date' => $customerCollection['created_at'],
                            'is_active' => $customerCollection['active'] == 'Yes' ? true : false,
                            'is_rental' => $customerCollection['cooperate_method'] == 2 ? true: false,
                            'is_profit_sharing' => $customerCollection['cooperate_method'] == 1 ? true: false,
                            'is_profit_sharing_percentage' => $customerCollection['commission_type'] == 2 ? true: false,
                            'is_both_utility_comm' => $customerCollection['commission_package'] == 1 ? true: false,
                            'product_unit_price' => $customerCollection['vending_piece_price'] ? $customerCollection['vending_piece_price'] * 100 : null,
                            'rental' => $customerCollection['vending_monthly_rental'] ? $customerCollection['vending_monthly_rental'] * 100 : null,
                            'profit_sharing' => $customerCollection['vending_profit_sharing'] ? $customerCollection['vending_profit_sharing'] * 100 : null,
                            'utilities' => $customerCollection['vending_monthly_utilities'] ? $customerCollection['vending_monthly_utilities'] * 100 : null,
                            'adjustment_rate' => $customerCollection['vending_clocker_adjustment'] ? $customerCollection['vending_clocker_adjustment'] * 100 : null,
                            'is_pwp' => $customerCollection['is_pwp'],
                            'pwp_adjustment_rate' => $customerCollection['pwp_adj_rate'] ? $customerCollection['pwp_adj_rate'] * 100 : null,
                        ]);
                    }
                }

            }
        }
    }
}
