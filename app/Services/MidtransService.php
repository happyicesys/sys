<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MidtransService
{
  const SANDBOX_BASE_URL = 'https://api.sandbox.midtrans.com';
  const PRODUCTION_BASE_URL = 'https://api.midtrans.com';

  public function charge($params)
  {
      $payloads = array(
          'payment_type' => 'qris'
      );

      if (isset($params['item_details'])) {
          $gross_amount = 0;
          foreach ($params['item_details'] as $item) {
              $gross_amount += $item['quantity'] * $item['price'];
          }
          $payloads['transaction_details']['gross_amount'] = $gross_amount;

          if($payloads['payment_type'] === 'qris') {
            $payloads['qris']['acquirer'] = 'gopay';
          }
      }

      $payloads = array_replace_recursive($payloads, $params);

      return Http::withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'User-Agent' => 'midtrans-php-v2.5.2',
        'Authorization' => 'Basic ' . base64_encode($server_key . ':')
        ])
        ->post(
          $this->getBaseUrl() . '/v2/charge',
          $payloads
        );
  }

  public function getBaseUrl()
  {
      return env('APP_ENV', 'production') == 'production' ?
      MidtransService::PRODUCTION_BASE_URL : MidtransService::SANDBOX_BASE_URL;
  }
}