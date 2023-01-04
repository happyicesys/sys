<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait OperatorAccess{

    public function scopeFilterOperatorAccess($query)
    {
      $operator = auth()->user()->operator;
      $isHappyIce = auth()->user()->operator->id == 1 ? true : false;
      if($isHappyIce) {
        $operator = null;
      }
      if($operator) {
        $query = $query->whereHas('operators', function($query) use ($operator) {
          $query->where('id', $operator->id);
        });
      }

      return $query;
    }

}