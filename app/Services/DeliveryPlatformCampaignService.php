<?php

namespace App\Services;

use App\Jobs\CreateDeliveryPlatformCampaign;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformCampaign;
use App\Models\DeliveryPlatformCampaignItemVend;
use App\Models\DeliveryPlatformOperator;
use App\Models\VendData;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformCampaignService
{
  private $model;

  public function __construct()
  {
    $this->model = new DeliveryPlatform();
  }

  public function createCampaign(DeliveryPlatformCampaignItemVend $deliveryPlatformCampaignItemVend)
  {
    $this->setDeliveryPlatform($deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug, $deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator);

    switch($deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $startTime = Carbon::now()->addMinutes(5)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
        $endTime = Carbon::now()->addMonths(2)->subDays(1)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $response = $this->model->createCampaign($this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend, [
          'startTime' => $startTime,
          'endTime' => $endTime,
        ]));

        VendData::create([
          'connection' => 'GRAB-CAMPAIGN',
          'processed' => $response,
          'value' => $this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend, [
            'startTime' => $startTime,
            'endTime' => $endTime,
          ]),
        ]);
        if($response['success']) {
          $deliveryPlatformCampaignItemVend->update([
            'datetime_from' => $startTime,
            'datetime_to' => $endTime,
            'is_submitted' => true,
            'platform_ref_id' => $response['data']['id'],
          ]);
          return $response['data'];
        }
        break;
      default:
        return;
    }
  }

  public function deleteCampaign(DeliveryPlatformCampaignItemVend $deliveryPlatformCampaignItemVend)
  {
    $this->setDeliveryPlatform($deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug, $deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator);

    switch($deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $response = $this->model->deleteCampaign($deliveryPlatformCampaignItemVend->platform_ref_id);

        VendData::create([
          'connection' => 'GRAB-CAMPAIGN-DELETE',
          'processed' => $response,
          'value' => $deliveryPlatformCampaignItemVend->platform_ref_id,
        ]);
        if($response['success']) {
          return $response['data'];
        }
        break;
      default:
        return;
    }
  }

  public function getItemOptions(DeliveryPlatformCampaign $deliveryPlatformCampaign)
  {
    switch($deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        return Grab::CAMPAIGN_MAPPINGS;
        break;
      default:
         return [];
    }
  }

  public function syncCampaigns(DeliveryPlatformCampaign $deliveryPlatformCampaign)
  {
      if($deliveryPlatformCampaign->deliveryPlatformCampaignItemVends()->exists()) {
          foreach($deliveryPlatformCampaign->deliveryPlatformCampaignItemVends as $deliveryPlatformCampaignItemVend) {
            if(!$deliveryPlatformCampaignItemVend->is_submitted) {
              CreateDeliveryPlatformCampaign::dispatch($deliveryPlatformCampaignItemVend)->onQueue('default');
            }
          }
      }
  }

  public function syncItemVends(DeliveryPlatformCampaign $deliveryPlatformCampaign)
  {
    if($deliveryPlatformCampaign->deliveryPlatformCampaignItems()->exists() and $deliveryPlatformCampaign->deliveryProductMapping->deliveryProductMappingVends()->whereNull('end_date')->exists()) {
      foreach($deliveryPlatformCampaign->deliveryProductMapping->deliveryProductMappingVends()->whereNull('end_date')->get() as $deliveryProductMappingVend) {
          foreach($deliveryPlatformCampaign->deliveryPlatformCampaignItems as $deliveryPlatformCampaignItem) {
              $deliveryProductMappingVend->deliveryPlatformCampaignItemVends()->updateOrCreate([
                  'delivery_platform_campaign_id' => $deliveryPlatformCampaign->id,
                  'delivery_platform_campaign_item_id' => $deliveryPlatformCampaignItem->id,
                  'settings_name' => $deliveryPlatformCampaignItem->settings_name,
                  'settings_label' => $deliveryPlatformCampaignItem->settings_label,
                  'settings_json' => $deliveryPlatformCampaignItem->settings_json,
              ], [
                  'is_active' => $deliveryPlatformCampaignItem->is_active,
              ]);
          }
      }
    }
    return true;
  }

  private function mapGrabCampaignParam($model, $params=[])
  {
    // from grab deliveryPlatformCampaignItemVend
    return $this->removeNullValuesRecursively([
      'merchantID' => $model->deliveryProductMappingVend->platform_ref_id,
      'name' => $model->settings_label,
      'quotas' => [
        'totalCount' => $model->settings_json['totalCount'] && $model->settings_json['totalCount'] != null ? intval($model->settings_json['totalCount']) : null,
        'totalCountPerUser' => $model->settings_json['totalCountPerUser'] && $model->deliveryPlatformCampaignItem->settings_json['totalCountPerUser'] != null ? intval($model->settings_json['totalCountPerUser']) : null,
      ],
      'conditions' => [
        'startTime' => $params['startTime'],
        'endTime' => $params['endTime'],
        'eaterType' => $model->settings_json['eaterType'],
        'minBasketAmount' => $model->settings_json['minBasketAmount'] && $model->settings_json['minBasketAmount'] != null ? $model->settings_json['minBasketAmount'] : 0,
        'bundleQuantity' => $model->settings_json['qty'] && $model->settings_json['qty'] != null ? intval($model->settings_json['qty']) : 0,
        'workingHour' => [
          'sun' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
          'mon' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
          'tue' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
          'wed' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
          'thu' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
          'fri' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
          'sat' => [
            'periods' => [
              [
                'startTime' => Grab::START_TIME,
                'endTime' => Grab::END_TIME,
              ],
            ],
          ],
        ]
      ],
      'discount' => [
        'type' => $model->settings_json['type'],
        'cap' => $model->settings_json['cap'] && $model->settings_json['cap'] != null ? intval($model->settings_json['cap']) : 0,
        'value' => floatval($model->settings_json['value']),
        'scope' => [
          'type' => $model->settings_json['scope'],
          'objectIDs' => $model->settings_json['objectIDs'],
        ]
      ],
      'customTag' => '',
    ]);
  }

  private function setDeliveryPlatform($slug, DeliveryPlatformOperator $deliveryPlatformOperator)
  {
    switch($slug) {
      case 'grab':
        $this->model = new Grab($deliveryPlatformOperator);
        break;
      default:
        return;
    }
  }

  protected function removeNullValuesRecursively($array)
  {
    $filteredArray = [];
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $filteredValue = $this->removeNullValuesRecursively($value);
            if (!empty($filteredValue)) {
                $filteredArray[$key] = $filteredValue;
            }
        } elseif (!is_null($value)) {
            $filteredArray[$key] = $value;
        }
    }
    return $filteredArray;

      // return array_filter($array, function ($value) {
      //     if (is_array($value)) {
      //         return $this->removeNullValuesRecursively($value);
      //     }
      //     return !is_null($value);
      // });
  }

}