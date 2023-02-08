<?php

namespace App\Jobs\Vend;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVendChannelErrorLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vend;
    protected $vendChannelCode;
    protected $vendChannelErrorCode;
    protected $vendTransactionId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Vend $vend, $vendChannelCode, $vendChannelErrorCode, $vendTransactionId = null)
    {
        $this->vend = $vend;
        $this->vendChannelCode = $vendChannelCode;
        $this->vendChannelErrorCode = $vendChannelErrorCode;
        $this->vendTransactionId = $vendTransactionId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vendChannelError = VendChannelError::where('code', $vendChannelErrorCode)->first();

        if($vendChannelError) {
            if($vendChannelError->code > 0) {
                $vendChannel = VendChannel::firstOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $vendChannelCode,
                ]);

                $lastVendChannelErrorLog = $vendChannel->vendChannelErrorLogs()->latest()->first();

                // dd($vendChannel->toArray(), $lastVendChannelErrorLog->toArray(), $lastVendChannelErrorLog->vendChannelError->code, $vendChannelErrorCode, $lastVendChannelErrorLog->is_error_cleared);

                if(!$lastVendChannelErrorLog or ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode) or $lastVendChannelErrorLog->is_error_cleared == 1) {
                    $vendChannelErrorLog = VendChannelErrorLog::create([
                        'vend_channel_id' => $vendChannel->id,
                        'vend_channel_error_id' => $vendChannelError->id
                    ]);

                    if($vendTransactionId) {
                        $vendChannelErrorLog->vend_transaction_id = $vendTransactionId;
                        $vendChannelErrorLog->save();
                    }

                    if($lastVendChannelErrorLog and ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode)) {
                        $lastVendChannelErrorLog->is_error_cleared = true;
                        $lastVendChannelErrorLog->save();
                    }

                    if($vendChannelErrorLog and !$vendTransactionId) {
                        $this->logVendChannelErrorNotTally($vendChannelErrorLog);
                    }
                }

            }else {
                $recoveredChannel = VendChannel::where('vend_id', $vend->id)->where('code', $vendChannelCode)->first();
                if($recoveredChannel) {
                    $recoveredVendChannelErrorLogs = VendChannelErrorLog::where('vend_channel_id', $recoveredChannel->id)->get();
                    if($recoveredVendChannelErrorLogs) {
                        foreach($recoveredVendChannelErrorLogs as $recoveredVendChannelErrorLog) {
                            $recoveredVendChannelErrorLog->is_error_cleared = true;
                            $recoveredVendChannelErrorLog->save();
                        }
                    }
                }

            }
            SaveVendChannelErrorLogsJson::dispatch($vend->id);
        }
    }
}
