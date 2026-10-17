<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * An operator-restricted viewer reaches only its own operator's incoming-batch files — on every
 * route, since view / delete find them by id (audit UI-02, 2026-10-17). Same viewer rule as the
 * vend grids (OperatorVendFilterScope::viewerOperatorId): HIPL sees all.
 */
class OperatorIncomingBatchAttachmentScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        if ($operatorId !== null) {
            $builder->where($model->getTable().'.operator_id', $operatorId);
        }
    }
}
