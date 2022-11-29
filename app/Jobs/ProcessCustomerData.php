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
    //
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
                // dd($customerCollection[0]);
                if($this->dataArr) {
                    $customerCollection = collect($customerCollection[0]);
                }
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

                if(isset($customerCollection['bank'])) {
                    $bankData = $customerCollection['bank'];
                    $bank = Bank::updateOrCreate([
                        'name' => $bankData['name']
                    ]);
                    $bankId = $bank->id;
                }

                if(isset($customerCollection['custcategory'])) {
                    $categoryData = $customerCollection['custcategory'];
                    if($categoryGroupData = $categoryData['custcategory_group']) {
                        $categoryGroup = CategoryGroup::updateOrCreate([
                            'name' => $categoryGroupData['name'],
                            'classname' => $className,
                        ], [
                            'desc' => isset($categoryGroupData['desc']) ?  $categoryGroupData['desc'] : null,
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

                if(isset($customerCollection['payterm'])) {
                    $paymentTermData = $customerCollection['payterm'];
                    $paymentTerm = PaymentTerm::updateOrCreate([
                        'name' => $paymentTermData
                    ]);
                    $paymentTermId = $paymentTerm->id;
                }

                if(isset($customerCollection['profile'])) {
                    $profileData = $customerCollection['profile'];
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

                if(isset($customerCollection['zone'])) {
                    $zoneData = $customerCollection['zone'];
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
                    'name' => isset($customerCollection['company']) ? $customerCollection['company'] : null,
                    'profile_id' => $profileId,
                    'status_id' => $statusId,
                    'bank_id' => $bankId,
                    'bank_remarks' => isset($customerCollection['account_number']) ? $customerCollection['account_number'] : null,
                    'category_id' => $categoryId,
                    'payment_term_id' => $paymentTermId,
                    'zone_id' => $zoneId,
                    'remarks' => isset($customerCollection['remark']) ? $customerCollection['remark'] : null,
                    'ops_note' => isset($customerCollection['operation_note']) ?  $customerCollection['operation_note'] : null,
                    'created_at' => $customerCollection['created_at'],
                ]);

                if(isset($customerCollection['billing_country']) and isset($customerCollection['bill_postcode'])) {
                    $billingCountry = $customerCollection['billing_country'];
                    $billingPostcode = $customerCollection['bill_postcode'];

                    $billingCountryCol = Country::where('name', $billingCountry['name'])->first();

                    if($billingCountryCol and $billingCountryCol->name == 'Singapore') {
                        $billingAddressResult = $this->getAddressResult($billingPostcode);

                        if($billingAddressResult) {
                            $customer->addresses()->updateOrCreate([
                                'type' => 1,
                            ], [
                                'unit_num' => isset($customerCollection['bill_address']) ? $customerCollection['bill_address'] : null,
                                'block_num' => $billingAddressResult['block_num'],
                                'street_name' => $billingAddressResult['street_name'],
                                'building' => $billingAddressResult['building'],
                                'full_address' => $billingAddressResult['full_address'],
                                'postcode' => $billingAddressResult['postcode'],
                                'latitude' => $billingAddressResult['latitude'],
                                'longitude' => $billingAddressResult['longitude'],
                                'country_id' => $billingCountryCol->id,
                            ]);
                        }
                    }
                }

                if(isset($customerCollection['delivery_country']) and isset($customerCollection['del_postcode'])) {
                    $deliveryCountry = $customerCollection['delivery_country'];
                    $deliveryPostcode = $customerCollection['del_postcode'];

                    $deliveryCountryCol = Country::where('name', $deliveryCountry['name'])->first();

                    if($deliveryCountryCol and $deliveryCountryCol->name == 'Singapore') {
                        $deliveryAddressResult = $this->getAddressResult($deliveryPostcode);

                        if($deliveryAddressResult) {
                            $customer->addresses()->updateOrCreate([
                                'type' => 2,
                            ], [
                                'unit_num' => isset($customerCollection['del_address']) ? $customerCollection['del_address'] : null,
                                'block_num' => $deliveryAddressResult['block_num'],
                                'street_name' => $deliveryAddressResult['street_name'],
                                'building' => $deliveryAddressResult['building'],
                                'full_address' => $deliveryAddressResult['full_address'],
                                'postcode' => $deliveryAddressResult['postcode'],
                                'latitude' => $deliveryAddressResult['latitude'],
                                'longitude' => $deliveryAddressResult['longitude'],
                                'country_id' => $deliveryCountryCol->id,
                            ]);
                        }
                    }
                }

                if($customerCollection['is_dvm'] or $customerCollection['is_vending'] or $customerCollection['is_combi']) {
                    $vend = Vend::where('code', $customerCollection['vend_code'])->first();
                    $vend->vendBindings()->update(['is_active' => false]);

                    if($vend) {
                        $customer->vendBinding()->updateOrCreate([
                            'vend_id' => $vend->id,
                            ],[
                            'begin_date' => $customerCollection['created_at'],
                            'is_active' => isset($customerCollection['active']) && $customerCollection['active'] == 'Yes' ? true : false,
                            'is_rental' => isset($customerCollection['cooperate_method']) && $customerCollection['cooperate_method'] == 2 ? true: false,
                            'is_profit_sharing' => isset($customerCollection['cooperate_method']) && $customerCollection['cooperate_method'] == 1 ? true: false,
                            'is_profit_sharing_percentage' => isset($customerCollection['commission_type']) && $customerCollection['commission_type'] == 2 ? true: false,
                            'is_both_utility_comm' => isset($customerCollection['commission_package']) && $customerCollection['commission_package'] == 1 ? true: false,
                            'product_unit_price' => isset($customerCollection['vending_piece_price']) ? $customerCollection['vending_piece_price'] * 100 : null,
                            'rental' => isset($customerCollection['vending_monthly_rental']) ? $customerCollection['vending_monthly_rental'] * 100 : null,
                            'profit_sharing' => isset($customerCollection['vending_profit_sharing']) ? $customerCollection['vending_profit_sharing'] * 100 : null,
                            'utilities' => isset($customerCollection['vending_monthly_utilities']) ? $customerCollection['vending_monthly_utilities'] * 100 : null,
                            'adjustment_rate' => isset($customerCollection['vending_clocker_adjustment']) ? $customerCollection['vending_clocker_adjustment'] * 100 : null,
                            'is_pwp' => isset($customerCollection['is_pwp']) ? $customerCollection['is_pwp'] : false,
                            'pwp_adjustment_rate' => isset($customerCollection['pwp_adj_rate']) ? $customerCollection['pwp_adj_rate'] * 100 : null,
                        ]);
                    }
                }
            }
        }
    }
}
