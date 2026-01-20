<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class SyncJobApkSetting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vendJob = null;
        if (isset($this->input['vend_job_id'])) {
            $vendJob = VendJob::find($this->input['vend_job_id']);
        } else {
            // Fallback: Find the latest unreturned job for this vend and type
            // Ensure we only match jobs that are within the current active retry window
            $vendJob = VendJob::where('vend_id', $this->vend->id)
                ->where('type', 'TYPESYNCSETTINGSPARAM') // Mapping JOBAPKSETTING response to original request type
                ->where('is_returned', false)
                ->where('updated_at', '>=', Carbon::now()->subMinutes(VendJob::RETRY_TIMEOUT_MINUTES))
                ->latest()
                ->first();
        }

        if ($vendJob) {
            $vendJob->update([
                'is_returned' => true,
                'response_at' => Carbon::now(),
                'response_payload' => $this->input
            ]);
        }
    }
}
