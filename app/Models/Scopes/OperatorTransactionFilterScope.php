<?php

namespace App\Models\Scopes;

use App\Support\VendAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorTransactionFilterScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (auth()->check() && auth()->user() instanceof \App\Models\User) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if ($isHappyIce) {
                $operatorId = null;
            }
            if ($operatorId) {
                $builder->where('vend_transactions.operator_id', $operatorId);
            }

            VendAccess::current()->constrain($builder, 'vend_transactions.vend_id', 'vend_transactions.customer_id');
        }
    }
}
