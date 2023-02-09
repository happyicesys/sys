<?php

namespace App\Jobs\Vend;

use App\Http\Resources\VendChannelErrorLogResource;
use App\Models\Vend;
use App\Models\VendChannelErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveVendChannelErrorLogsJson implements ShouldQueue
// implements ShouldQueue
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
            'vend_channel_error_logs_json' => VendChannelErrorLogResource::collection(VendChannelErrorLog::with(['vendChannel', 'vendChannelError'])->whereIn('vend_channel_id', $vend->vendChannels->pluck('id'))->where('is_error_cleared', false)->orderBy('created_at', 'desc')->get()),
        ]);
    }
}
