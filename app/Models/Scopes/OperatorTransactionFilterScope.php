<?php

namespace App\Models\Scopes;

use App\Models\Vend;
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
    if (auth()->check()) {
      $operatorId = auth()->user()->operator_id;
      $isHappyIce = $operatorId == 1 ? true : false;
      if ($isHappyIce) {
        $operatorId = null;
      }
      if ($operatorId) {
        $builder->where('vend_transactions.operator_id', $operatorId);
      }

      $user = auth()->user();
      $vendIds = $user->vends ? $user->vends->pluck('id')->toArray() : null;
      if ($vendIds) {
        $builder->whereIn('vend_transactions.vend_id', $vendIds);

        $customerIDs = Vend::whereIn('id', $vendIds)->pluck('customer_id')->toArray();

        if ($customerIDs) {
          $builder->whereIn('vend_transactions.customer_id', $customerIDs);
        }
      }
    }
  }
}
