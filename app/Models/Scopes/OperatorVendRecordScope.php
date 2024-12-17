<?php

namespace App\Models\Scopes;

use App\Models\Vend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorVendRecordScope implements Scope
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

            $customerIDs = Vend::whereIn('id', $vendIds)->get()->pluck('customer_id')->toArray();

            if($customerIDs) {
              $builder->whereIn('vend_records.customer_id', $customerIDs);
            }
        }
      }
    }
}
