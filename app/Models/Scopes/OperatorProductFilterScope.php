<?php

namespace App\Models\Scopes;

use App\Support\ProductOwners;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorProductFilterScope implements Scope
{
    /**
     * HIPL (operator 1) sees every product. Anyone else sees the products their
     * operator works with: its own, plus HIPL's for a member of HIPL's group
     * (Brian, 2026-10-27 — HIMD owns none and sells HIPL's) — App\Support\ProductOwners.
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (! auth()->check()) {
            return;
        }

        $operatorId = (int) auth()->user()->operator_id;

        if (! $operatorId || $operatorId === 1) {
            return;
        }

        // Qualified with the table name on purpose: this scope is registered on
        // Product, and any Product:: query that joins another table carrying an
        // operator_id column (vends, customers, operators, ...) would otherwise
        // die with "Column 'operator_id' in where clause is ambiguous".
        $builder->whereIn($model->getTable().'.operator_id', app(ProductOwners::class)->of($operatorId));
    }
}
