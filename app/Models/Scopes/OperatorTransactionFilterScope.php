<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorTransactionFilterScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
      if(auth()->check()) {
        $operatorId = auth()->user()->operator_id;
        $isHappyIce = $operatorId == 1 ? true : false;
        if($isHappyIce) {
          $operatorId = null;
        }
        if($operatorId) {
          $builder->whereHas('operator', function($query) use ($operatorId) {
            $query->where('id', $operatorId);
          });
        }

        $user = auth()->user();
        $vendIds = $user->vends ? $user->vends->pluck('id')->toArray() : null;
        if($vendIds) {
            $builder->whereIn('vend_id', $vendIds);
        }
      }
    }
}
