<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OperatorVendFilterScope implements Scope
{
    /** Operator whose staff see the whole fleet (HappyIce / HIPL). */
    public const UNRESTRICTED_OPERATOR_ID = 1;

    /**
     * The operator the current viewer is pinned to, or null when unrestricted.
     *
     * Exposed as a static because this scope only fires on Eloquent queries
     * rooted at Vend. Anything that reaches `vends` through a RAW JOIN - e.g.
     * MachineHealthDashboardService's channel-error query, which drives from
     * vend_channel_error_logs - never triggers it, and so has to apply the
     * identical predicate by hand. Callers must use this rather than
     * re-deriving "operator 1 means everything", so the rule keeps one
     * definition.
     *
     * Not the same rule as OperatorScope::current(), which resolves the HIPL
     * SIBLING GROUP for pages that opted into it. This is the narrower rule the
     * vend/transaction global scopes have always enforced; mixing the two would
     * silently move numbers on pages that use them.
     */
    public static function viewerOperatorId(): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        $operatorId = (int) auth()->user()->operator_id;

        if ($operatorId === self::UNRESTRICTED_OPERATOR_ID) {
            return null;
        }

        return $operatorId ?: null;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $operatorId = self::viewerOperatorId();

        if ($operatorId) {
            $builder->where('vends.operator_id', $operatorId);
        }
    }
}
