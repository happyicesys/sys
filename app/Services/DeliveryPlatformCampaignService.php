<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformCampaign;
use App\Models\DeliveryPlatformCampaignItemVend;
use App\Models\DeliveryPlatformOperator;
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
        if($response['success']) {
          $deliveryPlatformCampaignItemVend->update([
            'is_submitted' => true,
            'platform_ref_id' => $response['data']['id'],
            'submission_request_json' => $this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend),
            'submission_response_json' => $response,
          ]);
          return $response['data'];
        }else {
          $deliveryPlatformCampaignItemVend->update([
            'is_submitted' => false,
            'submission_request_json' => $this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend),
            'submission_response_json' => $response,
          ]);
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
        if($response['success']) {
          return $response['data'];
        }
        break;
      default:
        return;
    }
  }

  public function updateCampaign(DeliveryPlatformCampaignItemVend $deliveryPlatformCampaignItemVend)
  {
    $this->setDeliveryPlatform($deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug, $deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator);

    switch($deliveryPlatformCampaignItemVend->deliveryPlatformCampaign->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $response = $this->model->updateCampaign($deliveryPlatformCampaignItemVend->platform_ref_id, $this->mapGrabCampaignParam($deliveryPlatformCampaignItemVend));

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

  public function syncCampaigns(DeliveryPlatformCampaign $deliveryPlatformCampaign): array
  {
      $results = ['submitted' => [], 'failed' => [], 'skipped' => []];

      // Eager-load all relationships needed by validation + createCampaign/mapGrabCampaignParam
      // so we don't fire N+1 queries while iterating.
      $deliveryPlatformCampaign->load([
          'deliveryPlatformCampaignItemVends.deliveryProductMappingVend',
          'deliveryPlatformCampaignItemVends.deliveryPlatformCampaign.deliveryPlatformOperator.deliveryPlatform',
          'deliveryPlatformCampaignItemVends.deliveryPlatformCampaignItem',
      ]);

      $itemVends = $deliveryPlatformCampaign->deliveryPlatformCampaignItemVends;

      if ($itemVends->isEmpty()) {
          return $results;
      }

      foreach ($itemVends as $itemVend) {
          // Skip already-submitted or inactive records
          if ($itemVend->is_submitted || !$itemVend->is_active) {
              $results['skipped'][] = $itemVend->vend_code;
              continue;
          }

          // Pre-flight validation before hitting the Grab API
          $validationErrors = [];
          if (empty($itemVend->deliveryProductMappingVend?->platform_ref_id)) {
              $validationErrors[] = 'missing Grab merchant ID (platform_ref_id)';
          }
          if (empty($itemVend->datetime_to)) {
              $validationErrors[] = 'missing End Date';
          }
          if (!isset($itemVend->settings_json['value'])) {
              $validationErrors[] = 'missing promo value';
          }

          if (!empty($validationErrors)) {
              $results['failed'][] = [
                  'vend_code' => $itemVend->vend_code,
                  'error'     => implode(', ', $validationErrors),
              ];
              continue;
          }

          try {
              $response = $this->createCampaign($itemVend);
              if ($response) {
                  $results['submitted'][] = $itemVend->vend_code;
              } else {
                  // createCampaign returns null on failure; read the saved response for the reason
                  $savedResponse = $itemVend->fresh()->submission_response_json;
                  $errorMessage  = $savedResponse['data']['message']
                      ?? $savedResponse['message']
                      ?? 'Grab API returned an error';
                  $results['failed'][] = [
                      'vend_code' => $itemVend->vend_code,
                      'error'     => $errorMessage,
                  ];
              }
          } catch (\Exception $e) {
              $results['failed'][] = [
                  'vend_code' => $itemVend->vend_code,
                  'error'     => $e->getMessage(),
              ];
          }
      }

      return $results;
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
        'totalCountPerUser' => $model->settings_json['totalCountPerUser'] && $model->settings_json['totalCountPerUser'] != null ? intval($model->settings_json['totalCountPerUser']) : null,
      ],
      'conditions' => [
        'startTime' => Carbon::parse($model->datetime_from)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        'endTime' => Carbon::parse($model->datetime_to)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        'eaterType' => $model->settings_json['eaterType'],
        'minBasketAmount' => $model->settings_json['minBasketAmount'] && $model->settings_json['minBasketAmount'] != null ? floatval($model->settings_json['minBasketAmount']) : 0,
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