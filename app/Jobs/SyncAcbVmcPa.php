<?php

namespace App\Jobs;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAcbVmcPa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $input = $this->input;
        $vend = $this->vend;

        // TEMP DEBUG: trace job execution for vend 2004.
        $isDebug = (int) $vend->code === 2004;
        if ($isDebug) {
            \Log::channel('vend2004')->info('SyncAcbVmcPa handle start', [
                'vend_id' => $vend->id,
                'vend_code' => $vend->code,
                'input_keys' => array_keys((array) $input),
                'current_acb_vmc_pa_json_is_null' => $vend->acb_vmc_pa_json === null,
            ]);
        }

        $this->saveParameter($input, $vend);

        if ($isDebug) {
            $vend->refresh();
            \Log::channel('vend2004')->info('SyncAcbVmcPa handle done', [
                'vend_id' => $vend->id,
                'saved_acb_vmc_pa_json_keys' => is_array($vend->acb_vmc_pa_json) ? array_keys($vend->acb_vmc_pa_json) : '(not array)',
            ]);
        }
    }

    /**
     * Log job failure so silent queue errors surface in vend2004.log.
     */
    public function failed(\Throwable $e): void
    {
        if (isset($this->vend) && (int) $this->vend->code === 2004) {
            \Log::channel('vend2004')->error('SyncAcbVmcPa FAILED', [
                'vend_id' => $this->vend->id,
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'trace_first' => collect(explode("\n", $e->getTraceAsString()))->take(5)->implode("\n"),
            ]);
        }
    }

    private function saveParameter($input, Vend $vend)
    {
        $vend->acb_vmc_pa_json = $input;
        if ($vend->isDirty()) {
            $vend->save();
        }
    }
}
