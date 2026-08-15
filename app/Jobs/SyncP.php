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
        // 2026-08-14: only write fields the heartbeat actually carried. These
        // used to default to 0 / null when the key was absent, so a payload that
        // simply did not report the counter (every APK with debug mode off)
        // overwrote a real stored value with 0. Absent means "no news", not zero.
        $update = [];

        if (array_key_exists('OfflineRestartCount', $this->input)) {
            $update['offline_restart_count'] = $this->input['OfflineRestartCount'];
        }

        if (array_key_exists('OfflineRestartCountDatetime', $this->input)) {
            $update['offline_restart_count_datetime'] = $this->input['OfflineRestartCountDatetime'];
        }

        if ($update !== []) {
            $this->vend->update($update);
        }
    }
}
