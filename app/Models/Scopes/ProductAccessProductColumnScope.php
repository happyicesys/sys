<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Support\ProductAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * "Access Product(s)" restriction for any product-grained table that carries a
 * plain product_id column.
 *
 * Currently used by VendProductRecord and GpMetric - the two pre-aggregated
 * tables whose grain actually includes the product, so they CAN be filtered
 * honestly. (vend_records, customer_period_summaries and the fact_* tables are
 * aggregated ACROSS products and are hidden from restricted viewers instead.)
 *
 * Rows with a NULL product_id are excluded: an unattributable aggregate row is
 * not the restricted viewer's.
 */
class ProductAccessProductColumnScope implements Scope
{
    public function __construct(private string $column = 'product_id')
    {
    }

    public function apply(Builder $builder, Model $model)
    {
        if (! auth()->check() || ! auth()->user() instanceof User) {
            return;
        }

        $ids = ProductAccess::current();

        if ($ids === null) {
            return;
        }

        ProductAccess::applyToColumn($builder, $model->getTable() . '.' . $this->column, $ids);
    }
}
