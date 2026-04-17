<?php

namespace App\Jobs\Vend;

use App\Http\Resources\VendChannelErrorLogResource;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveVendChannelErrorLogsJson implements ShouldQueue, ShouldBeUnique
    // implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Deduplicate rapid-fire dispatches for the same vend within 60 seconds.
    // SyncVendChannels dispatches SyncVendChannelErrorLog once per active channel,
    // each of which then dispatches this job — ShouldBeUnique collapses those into one execution.
    public $uniqueFor = 60;

    public function uniqueId()
    {
        return 'vend_error_logs_' . $this->vendId;
    }

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
        // Only select id — we only need the primary key to run the update query
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)
            ->select('id')
            ->find($this->vendId);

        if (!$vend) {
            // Vend no longer exists (may have been deleted), skip silently
            return;
        }

        $vend->update([
            'vend_channel_error_logs_json' => VendChannelErrorLogResource::collection(
                VendChannelErrorLog::with(['vendChannel', 'vendChannelError'])
                    // Use a subquery instead of lazy-loading all VendChannels into memory
                    ->whereIn(
                        'vend_channel_id',
                        VendChannel::where('vend_id', $this->vendId)->select('id')
                    )
                    ->whereNotNull('vend_transaction_id')
                    ->where('is_error_cleared', false)
                    ->orderBy('created_at', 'desc')
                    ->get()
            ),
        ]);
    }
}
