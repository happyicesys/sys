<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Support\ProductAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * "Access Product(s)" restriction for the Product master itself.
 *
 * Narrows /products, the availability pages and every product dropdown built
 * from Product::.
 *
 * Strip this scope (withoutGlobalScope) wherever the FULL master is
 * legitimately required: blind-SKU child resolution, ops-job picking, and the
 * allow-list dropdowns on the User/Operator edit screens (otherwise a
 * restricted admin silently truncates the choices for everyone they edit).
 */
class ProductAccessProductScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (! auth()->check() || ! auth()->user() instanceof User) {
            return;
        }

        $ids = ProductAccess::current();

        if ($ids === null) {
            return;
        }

        ProductAccess::applyToColumn($builder, $model->getTable() . '.id', $ids);
    }
}
