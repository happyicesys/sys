<?php

namespace App\Jobs;

use App\Models\OpsJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveEmptyOpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;

    /**
     * Create a new job instance.
     */
    public function __construct($date)
    {
        $this->date = $date;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // "Empty" means no stop of ANY kind. Tasks, service notices and stock
        // checks all cascade-delete with their job, so a job holding only those
        // must survive the night — before 2026-09-19 a task-only job did not.
        // (Safe use of whereDoesntHave: none of these models carries a global scope.)
        OpsJob::query()
            ->whereDoesntHave('opsJobItems')
            ->whereDoesntHave('opsJobTasks')
            ->whereDoesntHave('serviceNotices')
            ->whereDoesntHave('stockChecks')
            ->where('date', '<=', Carbon::parse($this->date)->endOfDay())
            ->delete();
    }
}
