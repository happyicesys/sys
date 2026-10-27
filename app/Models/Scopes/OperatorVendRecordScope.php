<?php

namespace App\Models\Scopes;

use App\Support\VendAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorVendRecordScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
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
                $builder->whereHas('operator', function ($query) use ($operatorId) {
                    $query->where('id', $operatorId);
                });
            }

            VendAccess::current()->constrain($builder, $model->getTable().'.vend_id', $model->getTable().'.customer_id');
        }
    }
}
