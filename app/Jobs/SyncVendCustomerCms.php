<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Profile;
use App\Models\Status;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncVendCustomerCms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $callBackVendCodeEndPoint;
    protected $endPointUrl;
    protected $personID;
    protected $vendID;


    public function __construct($personID = null, $vendID = null)
    {
        $this->callBackVendCodeEndPoint = env('CMS_URL') . '/api/sys/person/' . $personID . '/vendcode/';
        $this->endPointUrl = env('CMS_URL') . '/api/person/migrate/' .  $personID;
        $this->personID = $personID;
        $this->vendID = $vendID;
    }

    public function handle()
    {
        $response = Http::get($this->endPointUrl);
        $customerCollection = $response->collect();

        $className = get_class(new Customer());
        $baseCurrencyCountryId = null;
        $categoryId = null;
        $categoryGroupId = null;
        $profileId = null;

        if($customerCollection and isset($customerCollection[0])) {
            $customerCollection = collect($customerCollection[0]);

                if(isset($customerCollection['location_type'])) {
                    $locationTypeData = $customerCollection['location_type'];
                    $locationType = LocationType::firstOrCreate([
                        'name' => $locationTypeData['name'],
                    ],
                    [
                        'remarks' => isset($locationTypeData['remarks']) ? $locationTypeData['remarks'] : null,
                        'sequence' => isset($locationTypeData['sequence']) ? $locationTypeData['sequence'] : null,
                    ]);
                    $locationTypeId = $locationType->id;
                }

                if(isset($customerCollection['profile'])) {
                    $profileData = $customerCollection['profile'];
                    $baseCurrencyCountryData = $profileData['currency'];
                    $baseCurrencyCountry = null;
                    // dd($profileData);
                    if(isset($baseCurrencyCountryData['currency_name'])) {
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
                            case 'IDR':
                                $baseCurrencyCountry = Country::updateOrCreate([
                                    'currency_name' => $baseCurrencyCountryData['currency_name'],
                                ], [
                                    'name' => 'Indonesia',
                                    'code' => 'ID',
                                    'currency_symbol' => 'Rp',
                                    'phone_code' => '62',
                                    'is_state' => false,
                                    'sequence' => 4,
                                ]);
                                $baseCurrencyCountryId = $baseCurrencyCountry->id;
                                break;
                            case 'THB':
                                $baseCurrencyCountry = Country::updateOrCreate([
                                    'currency_name' => $baseCurrencyCountryData['currency_name'],
                                ], [
                                    'name' => 'Thailand',
                                    'code' => 'TH',
                                    'currency_symbol' => '฿',
                                    'phone_code' => '66',
                                    'is_state' => false,
                                    'sequence' => 5,
                                ]);
                                $baseCurrencyCountryId = $baseCurrencyCountry->id;
                                break;
                        }
                    }

                    $profile = Profile::updateOrCreate([
                        'name' => $profileData['name']
                    ], [
                        'alias' => $profileData['acronym'],
                        'uen' => $profileData['roc_no'],
                        'base_currency_id' => isset($baseCurrencyCountryId) ? $baseCurrencyCountryId : null,
                    ]);
                    $profileId = $profile->id;
                }

            if($this->personID or isset($customerCollection['id'])) {
                $personID = $this->personID ? $this->personID : $customerCollection['id'];

                $customer = Customer::updateOrCreate([
                    'person_id' => $personID,
                ], [
                    'code' => $customerCollection['code'],
                    'person_json' => $customerCollection,
                    'account_manager_json' => isset($customerCollection['account_manager']) ? $customerCollection['account_manager'] : null,
                    'first_transaction_id' => isset($customerCollection['first_transaction_id']) ? $customerCollection['first_transaction_id'] : null,
                    'name' => isset($customerCollection['company']) ? $customerCollection['company'] : null,
                    'ops_note' => isset($customerCollection['operation_note']) ? $customerCollection['operation_note'] : null,
                    'profile_id' => $profileId,
                    'status_id' => Customer::STATUS_ACTIVE,
                    'location_type_id' => isset($locationTypeId) ? $locationTypeId : null,
                ]);

                // dd($customerCollection['delivery_country'], $customerCollection['del_postcode'], $customerCollection);
                if(isset($customerCollection['delivery_country']) and isset($customerCollection['del_postcode'])) {
                    $deliveryCountry = $customerCollection['delivery_country'];
                    $deliveryPostcode = $customerCollection['del_postcode'];
                    $deliveryCountryCol = Country::where('name', $deliveryCountry['name'])->first();

                    if($deliveryCountryCol and $deliveryCountryCol->name == 'Singapore') {
                        $customer->addresses()->updateOrCreate([
                            'type' => 2,
                        ], [
                            'latitude' => isset($customerCollection['del_lat']) ? $customerCollection['del_lat'] : null,
                            'longitude' => isset($customerCollection['del_lng']) ? $customerCollection['del_lng'] : null,
                            'street_name' => $customerCollection['del_address'],
                            'postcode' => $deliveryPostcode,
                            'country_id' => $deliveryCountryCol->id,
                        ]);
                    }
                }

                if(isset($customerCollection['name']) and isset($customerCollection['contact'])) {
                    $countryID = Country::where('name', $deliveryCountry['name'])->first();

                    $customer->contact()->updateOrCreate([
                        'name' => $customerCollection['name'],
                        'email' => isset($customerCollection['email']) ? $customerCollection['email'] : null,
                        'phone_num' => $customerCollection['contact'] ? $customerCollection['contact'] : null,
                        'phone_country_id' => $countryID,
                        'alt_phone_num' => isset($customerCollection['alt_contact']) && $customerCollection['alt_contact'] ? $customerCollection['alt_contact'] : null,
                        'alt_phone_country_id' => isset($customerCollection['alt_contact']) && $customerCollection['alt_contact'] ? $countryID : null,
                    ]);
                }

                if($this->vendID and Vend::find($this->vendID)) {
                    $beginDate =  isset($customerCollection['first_transaction_date']) ? $customerCollection['first_transaction_date'] : $customerCollection['created_at'];

                    $vend = Vend::findOrFail($this->vendID);

                    if($vend && $customer) {
                        $isExisting = Vend::where('customer_id', $customer->id)->first();

                        if(!$isExisting) {
                            $vend->update([
                                'customer_id' => $customer->id,
                            ]);
                        }
                        $vend->customer->update([
                            'begin_date' => Carbon::parse($beginDate),
                            'is_active' => true,
                            'termination_date' => null,
                        ]);
                    }

                    // call back point to cms to update vend code
                    $response = Http::get($this->callBackVendCodeEndPoint.$vend->code, [
                        'vend_prefix' => $vend->vendPrefix ? $vend->vendPrefix->name : null,
                    ]);
                }
            }


        }
    }
}
