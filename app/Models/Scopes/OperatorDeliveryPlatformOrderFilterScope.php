<?php

namespace App\Models\Scopes;

use App\Support\VendAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorDeliveryPlatformOrderFilterScope implements Scope
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
                $builder->whereHas('deliveryProductMappingVend.vend.operator', function ($query) use ($operatorId) {
                    $query->where('id', $operatorId);
                });
            }

            $access = VendAccess::current();
            if (! $access->isUnrestricted()) {
                $builder->whereHas('deliveryProductMappingVend.vend', fn ($query) => $access->constrain($query, 'vends.id'));
            }
        }
    }
}
