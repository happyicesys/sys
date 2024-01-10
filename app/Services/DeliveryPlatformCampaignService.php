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
        $response = $this->model->createCampaign($this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend));
        VendData::create([
          'connection' => 'GRAB-CAMPAIGN',
          'processed' => $response,
          'value' => $this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend),
        ]);
        if($response['success']) {
          $deliveryPlatformCampaignItemVend->update([
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
    if($deliveryPlatformCampaign->deliveryPlatformCampaignItems()->exists() and $deliveryPlatformCampaign->deliveryProductMapping->deliveryProductMappingVends()->exists()) {
      foreach($deliveryPlatformCampaign->deliveryProductMapping->deliveryProductMappingVends as $deliveryProductMappingVend) {
          foreach($deliveryPlatformCampaign->deliveryPlatformCampaignItems as $deliveryPlatformCampaignItem) {
              $deliveryProductMappingVend->deliveryPlatformCampaignItemVends()->updateOrCreate([
                  'delivery_platform_campaign_id' => $deliveryPlatformCampaign->id,
                  'delivery_platform_campaign_item_id' => $deliveryPlatformCampaignItem->id,
              ], [
                  'is_active' => $deliveryPlatformCampaignItem->is_active,
              ]);
          }
      }
    }
    return true;
  }

  private function mapGrabCampaignParam($model)
  {
    // from grab deliveryPlatformCampaignItemVend
    return $this->removeNullValuesRecursively([
      'merchantID' => $model->deliveryProductMappingVend->platform_ref_id,
      'name' => $model->deliveryPlatformCampaignItem->settings_label,
      'quotas' => [
        'totalCount' => $model->deliveryPlatformCampaignItem->settings_json['totalCount'] ? intval($model->deliveryPlatformCampaignItem->settings_json['totalCount']) : null,
        'totalCountPerUser' => $model->deliveryPlatformCampaignItem->settings_json['totalCountPerUser'] ? intval($model->deliveryPlatformCampaignItem->settings_json['totalCountPerUser']) : null,
      ],
      'conditions' => [
        'startTime' => Carbon::parse($model->deliveryPlatformCampaignItem->datetime_from)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        'endTime' => Carbon::parse($model->deliveryPlatformCampaignItem->datetime_to)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        'eaterType' => $model->deliveryPlatformCampaignItem->settings_json['eaterType'],
        'minBasketAmount' => $model->deliveryPlatformCampaignItem->settings_json['minBasketAmount'] ? intval($model->deliveryPlatformCampaignItem->settings_json['minBasketAmount']) : null,
        'bundleQuantity' => $model->deliveryPlatformCampaignItem->settings_json['qty'] ? intval($model->deliveryPlatformCampaignItem->settings_json['qty']) : null,
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
        'type' => $model->deliveryPlatformCampaignItem->settings_json['type'],
        'cap' => $model->deliveryPlatformCampaignItem->settings_json['cap'] ? intval($model->deliveryPlatformCampaignItem->settings_json['cap']) : null,
        'value' => intval($model->deliveryPlatformCampaignItem->settings_json['value']),
        'scope' => [
          'type' => $model->deliveryPlatformCampaignItem->settings_json['scope'],
          'objectIDs' => $model->deliveryPlatformCampaignItem->settings_json['objectIDs'],
        ]
      ],
      'customTag' => null,
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

  protected function removeNullValuesRecursively(&$array)
  {
      foreach ($array as $key => &$value) {
          if (is_array($value)) {
              $this->removeNullValuesRecursively($value);
          }
          if ($value === null) {
              unset($array[$key]);
          }
      }
  }

}