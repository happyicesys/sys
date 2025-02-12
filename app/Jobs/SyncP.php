<?php

namespace App\Jobs;

use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncP implements ShouldQueue
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
        $this->vend->update([
            'offline_restart_count' => isset($this->input['OfflineRestartCount']) ? $this->input['OfflineRestartCount'] : 0,
            'offline_restart_count_datetime' => isset($this->input['OfflineRestartCountDatetime']) ? $this->input['OfflineRestartCountDatetime'] : null,
        ]);
    }
}
