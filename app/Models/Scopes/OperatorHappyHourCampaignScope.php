<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Operator isolation for Happy Hour campaigns: an operator-restricted viewer sees only campaigns its
 * own operator owns (`happy_hour_campaigns.operator_id`); HIPL (operator 1) and unauthenticated
 * callers (the minute planner) see all — `OperatorVendFilterScope::viewerOperatorId()`.
 *
 * Owner only, not "has one of my machines": a campaign may span operators (HIPL can bind any
 * freezer), and editing it would re-price the other operator's machines. A GLOBAL scope because the
 * controller reaches campaigns by id (update, destroy, preview), not only through index().
 *
 * Code that must judge every campaign whoever is looking — the overlap check, the planner, the
 * Sales Transactions badge — queries with `withoutGlobalScope(self::class)`.
 */
class OperatorHappyHourCampaignScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        if ($operatorId) {
            $builder->where($model->qualifyColumn('operator_id'), $operatorId);
        }
    }
}
