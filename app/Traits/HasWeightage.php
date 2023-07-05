<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait HasWeightage {

  public function recalculateAllWeightage($className)
  {
    $model = new $className;
    $allWeightage = $model->sum('weightage');

    if($allWeightage > 100){
      // dd($allWeightage);
      $items = $model->all();
      foreach($items as $item) {
        $item->weightage = $item->weightage/ $allWeightage * 100;
        $item->save();
      }
    }
  }

}