<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorUserTransactionFilterScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user() instanceof \App\Models\User) {
            $vendIds = auth()->user()->vends->pluck('id');
            if ($vendIds->isNotEmpty()) {
                $builder->whereIn('vend_transactions.vend_id', $vendIds);
            }
        }
    }
}
