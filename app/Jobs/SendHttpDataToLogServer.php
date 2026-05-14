<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * DISABLED — we now log to the local `vend_data` table via the CreateVendData job
 * (see VendDataService::processVendData).
 *
 * Class kept (rather than deleted) so any queued jobs still sitting in the queue
 * at deploy time can be deserialized by the worker without throwing
 * "Class not found" errors. Once you're confident the queue has fully drained
 * you can delete this file.
 *
 * To re-enable: restore the previous handle() body from git history AND uncomment
 * the dispatch block in VendDataService::processVendData.
 */
class SendHttpDataToLogServer implements ShouldQueue
{
    use Queueable;

    /** Don't retry — this job is intentionally a no-op now. */
    public $tries = 1;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        // No-op. See class doc-block.
        return;
    }
}
