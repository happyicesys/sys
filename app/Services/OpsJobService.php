<?php

namespace App\Services;

use App\Models\OpsJob;
use App\Models\VendChannel;
use Carbon\Carbon;

class OpsJobService
{

  public function getItemChannels($vendChannels)
  {
    if($vendChannels) {
      foreach($vendChannels as $vendChannel) {
        $channels[] = [
          'id' => $vendChannel->id,
          'actual_qty' => $vendChannel->actual_qty,
          'capacity' => $vendChannel->capacity,
          'channel_code' => $vendChannel->code,
          'product_id' => $vendChannel->product->id,
          'product_code' => $vendChannel->product->code,
          'product_name' => $vendChannel->product->name,
          'qty' => $vendChannel->qty,
          'thumbnail_url' => $vendChannel->product->thumbnail->full_url,
        ];
      }
    }
  }
}