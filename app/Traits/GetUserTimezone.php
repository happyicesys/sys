<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait GetUserTimezone
{

  public function getUserTimezone()
  {
    // Memoized per authenticated user for the lifetime of the request.
    // This method is called up to ~30x per resource row (dozens of resources
    // use this trait), and the original expression rebuilt an Eloquent
    // sub-query builder via has('operator') on EVERY call — pure CPU waste.
    // has('operator') returns a (always-truthy) Builder, so the resolved
    // value is identical: operator timezone when logged in, app default
    // otherwise. Output is unchanged.
    static $memo = [];

    $userId = auth()->id() ?? 0;

    if (!array_key_exists($userId, $memo)) {
      $memo[$userId] = auth()->user() && auth()->user()->has('operator')
        ? auth()->user()->operator->timezone
        : config('app.timezone');
    }

    return $memo[$userId];
  }
}