<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorProductFilterScope implements Scope
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
          // Qualified with the table name on purpose: this scope is registered on
          // Product, and any Product:: query that joins another table carrying an
          // operator_id column (vends, customers, operators, ...) would otherwise
          // die with "Column 'operator_id' in where clause is ambiguous".
          $builder->where($model->getTable() . '.operator_id', $operatorId);
        }
      }
    }
}
