<?php

namespace App\Services;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\VendChannel;
use Carbon\Carbon;

class VendChannelService
{
  public function syncAllVendChannelsJson($vendIDArray = [])
  {
    $vendIDs = array_unique($vendIDArray);

    foreach($vendIDs as $vendID) {
        SaveVendChannelsJson::dispatch($vendID)->onQueue('default');
    }
  }


}