<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ErrorService
{
  public function throwErrorWithMqtt($message, Vend $vend)
  {
    PublishMqtt::dispatch('CV'.$vend->code, $message)->onQueue('high');
    throw new \Exception($message);
  }

  public function throwError($message)
  {
    throw new \Exception($message);
  }
}