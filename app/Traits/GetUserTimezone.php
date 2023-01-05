<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait GetUserTimezone{

  public function getUserTimezone()
  {
    $timezone = auth()->user()->has('operator') ? auth()->user()->operator->timezone : 'Asia/Singapore';

    return $timezone;
  }
}