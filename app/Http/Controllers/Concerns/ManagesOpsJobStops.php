<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Support\Collection;

/**
 * What the controllers of machine-bound ops-job stops (service notices, stock
 * checks) share beyond the viewer ceiling.
 */
trait ManagesOpsJobStops
{
    use ScopesOpsJobToViewer;

    /**
     * The machine a stop is opened for must be one the VIEWER may see — the
     * same rule the job page's machine dropdown follows (Vend's global scope:
     * operator 1 sees the fleet, everyone else their own operator).
     *
     * Deliberately NOT "the machine's operator must equal the job's": operator-1
     * jobs routinely carry sibling operators' machines (prod, Aug–Sep 2026:
     * ~16% of ops_job_items sit on a vend of another operator than their job).
     */
    protected function vendVisibleToViewer(int $vendId): Vend
    {
        return Vend::query()->findOrFail($vendId);
    }

    /**
     * People who have had a job assigned — the "Assigned To" filter of a stop
     * listing. `users` carries no global scope (CLAUDE.md), so the picker
     * applies the operator boundary itself.
     */
    protected function stopDriverOptions(): Collection
    {
        $viewerOperatorId = OperatorVendFilterScope::viewerOperatorId();

        return User::query()
            ->select('id', 'name')
            ->when($viewerOperatorId !== null, fn ($q) => $q->where('operator_id', $viewerOperatorId))
            ->whereIn('id', fn ($sub) => $sub->select('delivered_by')->from('ops_jobs')->whereNotNull('delivered_by'))
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['id' => (string) $u->id, 'value' => $u->name]);
    }
}
