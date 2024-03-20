<?php

namespace App\Services;

use App\Models\VendTemp;
use Carbon\Carbon;

class VendTempService
{

  protected $vendTemp;

  public function __construct(VendTemp $vendTemp)
  {
    $this->vendTemp = $vendTemp;
  }

  public function compareLast($type)
  {
    $tempArr = $this
                ->vendTemp
                ->vend
                ->vendTemps()
                ->where('vend_type', $type)
                ->latest()
                ->take(2)
                ->get();

    if ($tempArr->count() == 2) {
      $diff = $tempArr[0]->value - $tempArr[1]->value;
      if ($diff > 0) {
        dd($diff);
        return true;
      }
    }
    return false;
  }

  public function logicProcessing()
  {

  }
}