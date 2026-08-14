<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Operator isolation for Grab Product Mapping (/delivery-product-mappings).
 *
 * A viewer sees a mapping when EITHER is true:
 *
 *   1. they own it   - delivery_product_mappings.operator_id is theirs
 *   2. it is bound to one of their machines, via an ACTIVE row in
 *      delivery_product_mapping_vend (end_date IS NULL)
 *
 * Both arms are needed, and dropping either breaks something real:
 *
 * - Without (1), an operator loses sight of a mapping the moment it has no
 *   active binding - including a mapping they have just created and not yet
 *   bound, which makes the create-then-bind flow impossible to complete. This
 *   is not hypothetical: of HIPL's 28 mappings only 9 currently have an active
 *   bind, and operator AE's single mapping has none (checked in prod
 *   2026-08-14).
 * - Without (2), an operator cannot see a mapping that another operator owns
 *   but that is bound to THEIR machine - which is the case the page exists to
 *   show them.
 *
 * Operator 1 (HappyIce/HIPL) is unrestricted and unauthenticated callers are
 * unrestricted, matching OperatorVendFilterScope - the Grab webhook routes
 * (routes/api.php, 'delivery.authprobe' only LOGS, it does not authenticate)
 * therefore keep seeing every mapping, which order ingestion depends on.
 *
 * Deliberately a GLOBAL scope, not a filter in the controller's index(): the
 * same model is fetched by findOrFail() in edit/update/delete/bindVend/
 * togglePause/saveBundleSales, and by DeliveryProductMapping::all() for two
 * option lists. A scope covers the row-level actions and the dropdowns in one
 * place; a WHERE in index() would have left every one of those reachable by id.
 */
class OperatorDeliveryProductMappingScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        if (! $operatorId) {
            return;
        }

        // Grouped so the OR cannot escape and swallow the other predicates.
        $builder->where(function ($query) use ($operatorId) {
            $query
                ->where('delivery_product_mappings.operator_id', $operatorId)
                ->orWhereExists(function ($sub) use ($operatorId) {
                    $sub->selectRaw('1')
                        ->from('delivery_product_mapping_vend')
                        ->join('vends', 'vends.id', '=', 'delivery_product_mapping_vend.vend_id')
                        ->whereColumn(
                            'delivery_product_mapping_vend.delivery_product_mapping_id',
                            'delivery_product_mappings.id'
                        )
                        ->whereNull('delivery_product_mapping_vend.end_date')
                        ->where('vends.operator_id', $operatorId);
                });
        });
    }
}
