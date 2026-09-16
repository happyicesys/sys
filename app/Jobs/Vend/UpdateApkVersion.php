<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Services\VendDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateApkVersion implements ShouldQueue
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
        $this->vend->update([
            'apk_ver_json' => $this->input ? $this->input : null,
        ]);

        // The MQTT ack gate caches this column (VendDataService); a frame that
        // arrived between the PWRON forget and this write may have re-cached
        // the old version, so drop it again now that the new one is stored.
        Cache::forget(VendDataService::apkVerCacheKey($this->vend->id));
    }
}
