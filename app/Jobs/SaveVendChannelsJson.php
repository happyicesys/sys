<?php

namespace App\Jobs;

use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveVendChannelsJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vendId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($vendId)
    {
        $this->vendId = $vendId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vend = Vend::findOrFail($this->vendId);
        $vend->update([
            'vend_channels_json' => $vend->vendChannels,
        ]);
    }
}
