<?php

namespace App\Models\Scopes;

use App\Models\Vend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Operator isolation for UI Settings (/apk-settings).
 *
 * A non-HappyIce viewer sees a setting when EITHER is true:
 *
 *   1. they own it   - apk_settings.operator_id is theirs (set on create)
 *   2. it is bound to one of their machines, via apk_setting_vend
 *
 * Both arms are needed. Without (1) a setting is invisible until something is
 * bound to it, and binding happens on its Edit page - so an operator could
 * never open a setting they had just created (EATZ, 2026-10-07: store()
 * redirected to edit, findOrFail 404'd, six times). Without (2) an operator
 * loses sight of the HappyIce-owned settings their machines run on.
 *
 * Operator 1 (HappyIce) and unauthenticated callers are unrestricted, as in
 * OperatorVendFilterScope - the machine-facing /parameters path has no user.
 *
 * A global scope rather than a filter in index(): every write endpoint on the
 * controller reaches the row by findOrFail($id).
 *
 * Users pinned to specific machines (user_vend) are further narrowed to
 * settings bound to those machines - unchanged from before.
 */
class OperatorApkSettingScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (! auth()->check()) {
            return;
        }

        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        if ($operatorId) {
            // Grouped so the OR cannot escape and swallow other predicates.
            $builder->where(function ($query) use ($operatorId) {
                $query
                    ->where('apk_settings.operator_id', $operatorId)
                    ->orWhereExists(function ($sub) use ($operatorId) {
                        $sub->selectRaw('1')
                            ->from('apk_setting_vend')
                            ->join('vends', 'vends.id', '=', 'apk_setting_vend.vend_id')
                            ->whereColumn('apk_setting_vend.apk_setting_id', 'apk_settings.id')
                            ->where('vends.operator_id', $operatorId);
                    });
            });
        }

        $user = auth()->user();
        $vendIds = $user->vends ? $user->vends->pluck('id')->toArray() : null;

        if ($vendIds) {
            // Ensure filtering by vends the user is associated with
            $builder->whereHas('vends', function ($query) use ($vendIds) {
                $query->whereIn('vends.id', $vendIds);
            });
            // Get customers linked to those vends
            $customerIDs = Vend::whereIn('id', $vendIds)->pluck('customer_id')->toArray();

            if ($customerIDs) {
                $builder->whereHas('vends.customer', function ($query) use ($customerIDs) {
                    $query->whereIn('customers.id', $customerIDs);
                });
            }
        }
    }
}
