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
    protected $personId;
    protected $vendId;


    public function __construct($vendId = null, $personId = null)
    {
        $this->callBackVendCodeEndPoint = env('CMS_URL') . '/api/person/' . $personId . '/vendcode/';
        $this->endPointUrl = env('CMS_URL') . '/api/person/migrate/' .  $personId;
        $this->personId = $personId;
        $this->vendId = $vendId;
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
                    $locationType = LocationType::updateOrCreate([
                        'name' => $locationTypeData['name'],
                    ],
                    [
                        'remarks' => isset($locationTypeData['remarks']) ? $locationTypeData['remarks'] : null,
                        'sequence' => isset($locationTypeData['sequence']) ? $locationTypeData['sequence'] : null,
                    ]);
                    $locationTypeId = $locationType->id;
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
                }

            if($this->personId) {
                $customer = Customer::updateOrCreate([
                    'person_id' => $this->personId,
                ], [
                    'code' => $customerCollection['code'],
                    'person_json' => $customerCollection,
                    'account_manager_json' => isset($customerCollection['account_manager']) ? $customerCollection['account_manager'] : null,
                    'first_transaction_id' => isset($customerCollection['first_transaction_id']) ? $customerCollection['first_transaction_id'] : null,
                    'name' => isset($customerCollection['company']) ? $customerCollection['company'] : null,
                    'profile_id' => $profileId,
                    'status_id' => Customer::STATUS_ACTIVE,
                    'category_id' => $categoryId,
                    'location_type_id' => isset($locationTypeId) ? $locationTypeId : null,
                    'created_at' => $customerCollection['created_at'],
                ]);
                dd($customerCollection['delivery_country'], $customerCollection['del_postcode'], $customerCollection);
                if(isset($customerCollection['delivery_country']) and isset($customerCollection['del_postcode'])) {
                    $deliveryCountry = $customerCollection['delivery_country'];
                    $deliveryPostcode = trim($customerCollection['del_postcode']);

                    $deliveryCountryCol = Country::where('name', $deliveryCountry['name'])->first();

                    if($deliveryCountryCol and $deliveryCountryCol->name == 'Singapore') {
                        $customer->addresses()->updateOrCreate([
                            'type' => 2,
                        ], [
                            'postcode' => $deliveryPostcode,
                            'country_id' => $deliveryCountryCol->id,
                        ]);
                    }
                }
            }

            if($this->vendId) {
                $beginDate = isset($customerCollection['first_transaction_date']) ? $customerCollection['first_transaction_date'] : $customerCollection['created_at'];
                if($beginDate and Carbon::parse($beginDate)->lt(Carbon::parse('2023-01-01')->startOfDay())) {
                    $beginDate = '2023-01-01';
                }

                $vend = Vend::findOrFail($this->vendId);
                $customer->latestVendBinding()->updateOrCreate([
                    'vend_id' => $vend->id,
                    'customer_id' => $customer->id,
                    ],[
                    'begin_date' => $beginDate,
                    'person_id' => $customerCollection['id'],
                ]);

                // call back point to cms to update vend code
                Http::get($this->callBackVendCodeEndPoint.$vend->code);
            }
        }
    }
}
