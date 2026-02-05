<?php

namespace App\Services;
use App\Models\Customer;
use App\Models\Vend;
use Carbon\Carbon;

class RunningNumberService
{
  public function getVendOrderID(Vend $vend)
  {
    $operatorTimezone = config('app.timezone');
    if ($vend->operator) {
      $operatorTimezone = $vend->operator->timezone;
    }
    return Carbon::now()->setTimeZone($operatorTimezone)->format('ymdHis') . sprintf('%05d', $vend->code);
  }

  public function getVendOrderIDBasedOnDate(Vend $vend, $date)
  {
    $operatorTimezone = config('app.timezone');
    if ($vend->operator) {
      $operatorTimezone = $vend->operator->timezone;
    }
    return Carbon::parse($date)->setTimeZone($operatorTimezone)->format('ymdHis') . sprintf('%05d', $vend->code);
  }

  public function getCustomerRunningCode($operatorID = null)
  {
    if (!$operatorID) {
      $operatorID = auth()->user()->operator_id;
    }

    $previousRunningNumber = Customer::where('operator_id', $operatorID)->max('code');

    return $previousRunningNumber ? intval($previousRunningNumber) + 1 : 10001;
  }

  public function getRunningCode($model, $operatorID = null)
  {
    if (!$operatorID) {
      $operatorID = auth()->user()->operator_id;
    }

    $previousRunningNumber = $model->where('operator_id', $operatorID)->max('code');

    return $previousRunningNumber ? intval($previousRunningNumber) + 1 : 10001;
  }
}