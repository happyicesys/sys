<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OpsJob;
use App\Models\Scopes\OperatorVendFilterScope;

/**
 * The operator ceiling for anything reached through an ops job. One definition,
 * shared by OpsJobController and the controllers of the stops that ride on a
 * job (service notices, stock checks).
 */
trait ScopesOpsJobToViewer
{
    /**
     * 404 when the job belongs to an operator the viewer may not see. Every
     * job carries operator_id (NOT NULL; live data: it always equals the
     * driver's operator), so this is the cheapest correct boundary. Deliberately
     * NOT a global scope on OpsJob — the cron / driver API paths must keep
     * reading every job.
     */
    protected function assertWithinViewerCeiling(?OpsJob $opsJob): OpsJob
    {
        abort_if($opsJob === null, 404);

        $viewerOperatorId = OperatorVendFilterScope::viewerOperatorId();

        abort_if($viewerOperatorId !== null && (int) $opsJob->operator_id !== $viewerOperatorId, 404);

        return $opsJob;
    }

    protected function scopedOpsJob($id): OpsJob
    {
        return $this->assertWithinViewerCeiling(OpsJob::find($id));
    }
}
