<?php

namespace App\Services;
use App\Models\Customer;
use App\Models\Vend;
use Carbon\Carbon;

class RunningNumberService
{
  public function getVendOrderID(Vend $vend)
  {
    $operatorTimezone = 'Asia/Singapore';
    if($vend->operators()->exists()) {
        $operatorTimezone = $vend->operators()->first()->timezone;
    }
    return Carbon::now()->setTimeZone($operatorTimezone)->format('ymdhis').sprintf('%05d', $vend->code);
  }

  public function getCustomerRunningCode($operatorID = null)
  {
    if(!$operatorID) {
        $operatorID = auth()->user()->operator_id;
    }

    $previousRunningNumber = Customer::where('operator_id', $operatorID)->max('code');

    return $previousRunningNumber ? intval($previousRunningNumber) + 1 : 10001;
  }
}