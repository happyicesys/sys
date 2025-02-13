<?php

namespace App\Models\Scopes;

use App\Models\Vend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorApkSettingScope implements Scope
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
            $isHappyIce = $operatorId == 1; // Exclude operator_id == 1

            if (!$isHappyIce && $operatorId) {
                // Filter ApkSettings that have related vends with the auth user's operator_id
                $builder->whereHas('vends', function ($query) use ($operatorId) {
                    $query->where('operator_id', $operatorId);
                });
            }

            $user = auth()->user();
            $vendIds = $user->vends ? $user->vends->pluck('id')->toArray() : null;

            if ($vendIds) {
                // Ensure filtering by vends the user is associated with
                $builder->whereHas('vends', function ($query) use ($vendIds) {
                    $query->whereIn('id', $vendIds);
                });

                // Get customers linked to those vends
                $customerIDs = Vend::whereIn('id', $vendIds)->pluck('customer_id')->toArray();

                if ($customerIDs) {
                    $builder->whereHas('vends.customer', function ($query) use ($customerIDs) {
                        $query->whereIn('id', $customerIDs);
                    });
                }
            }
        }
    }
}
