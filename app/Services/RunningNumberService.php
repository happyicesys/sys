<?php

namespace App\Services;
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
}