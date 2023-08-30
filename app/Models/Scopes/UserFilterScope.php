<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UserFilterScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if(auth()->check()) {
            if(auth()->user()->vends()->exists()) {
                $builder->whereHas('vends', function($query) {
                    $query->whereIn('id', auth()->user()->vends->pluck('id'));
                });
            }
        }
    }
}
