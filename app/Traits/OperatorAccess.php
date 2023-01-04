<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait OperatorAccess{

    public function filterOperatorAccess($query)
    {
      $operator = auth()->user()->operator;
      // $query = $query->whereHas()
    }

}