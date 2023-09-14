<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformOperator;
use App\Models\Operator;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformService
{
  private $deliveryPlatform;
  private $deliveryPlatformOperator;
  private $operator;

  public function __construct()
  {

  }

  // public function

  private function outgoingOauthParams($params = [])
  {
    return [
      'access_token' => $params['access_token'],
      'token_type' => $params['type'],
      'expires_in' => $params['expired_at'],
    ];
  }

}