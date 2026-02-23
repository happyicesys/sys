<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendLog;
use App\Jobs\Vend\SaveVendChannelErrorLogsJson;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log as Logger;
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
        $vend = $this->vend;
        $vendChannelCode = $this->vendChannelCode;
        $vendChannelErrorCode = $this->vendChannelErrorCode;
        $vendTransactionId = $this->vendTransactionId;

        $vendChannelError = VendChannelError::where('code', $vendChannelErrorCode)->first();

        if ($vendChannelError) {
            if ($vendChannelError->code > 0) {
                $vendChannel = VendChannel::firstOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $vendChannelCode,
                ]);

                $lastVendChannelErrorLog = $vendChannel->vendChannelErrorLogs()->latest()->first();

                // dd($vendChannel->toArray(), $lastVendChannelErrorLog->toArray(), $lastVendChannelErrorLog->vendChannelError->code, $vendChannelErrorCode, $lastVendChannelErrorLog->is_error_cleared);

                if ($vendTransactionId or !$lastVendChannelErrorLog or ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode) or $lastVendChannelErrorLog->is_error_cleared == 1) {
                    $vendChannelErrorLog = VendChannelErrorLog::create([
                        'vend_channel_id' => $vendChannel->id,
                        'vend_channel_error_id' => $vendChannelError->id
                    ]);

                    if (Schema::hasTable('vend_logs')) {
                        try {
                            VendLog::create([
                                'vend_id' => $vend->id,
                                'event' => VendLog::EVENT_CHANNEL_ERROR,
                                'subject' => sprintf('Channel %s error %s', $vendChannelCode, $vendChannelErrorCode),
                                'context' => [
                                    'vend_channel_error_log_id' => $vendChannelErrorLog->id,
                                    'channel_code' => $vendChannelCode,
                                    'error_code' => $vendChannelErrorCode,
                                ],
                                'occurred_at' => now(),
                            ]);
                        } catch (\Throwable $e) {
                            Logger::warning('Failed to create vend log for channel error', [
                                'vend_id' => $vend->id,
                                'channel_code' => $vendChannelCode,
                                'error_code' => $vendChannelErrorCode,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($vendTransactionId) {
                        $vendChannelErrorLog->vend_transaction_id = $vendTransactionId;
                        $vendChannelErrorLog->save();
                    }

                    if ($lastVendChannelErrorLog and ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode)) {
                        $lastVendChannelErrorLog->is_error_cleared = true;
                        $lastVendChannelErrorLog->save();

                        if (Schema::hasTable('vend_logs')) {
                            VendLog::create([
                                'vend_id' => $vend->id,
                                'event' => 'machine_health_alert_dismissed',
                                'subject' => sprintf('Channel %s error %s cleared', $lastVendChannelErrorLog->vendChannel->code, $lastVendChannelErrorLog->vendChannelError->code),
                                'context' => [
                                    'channel_code' => $lastVendChannelErrorLog->vendChannel->code,
                                    'error_code' => $lastVendChannelErrorLog->vendChannelError->code,
                                ],
                                'occurred_at' => now(),
                            ]);
                        }
                    }
                }

            } else {
                $recoveredChannel = VendChannel::where('vend_id', $vend->id)->where('code', $vendChannelCode)->first();
                if ($recoveredChannel) {
                    $recoveredVendChannelErrorLogs = VendChannelErrorLog::where('vend_channel_id', $recoveredChannel->id)
                        ->where('is_error_cleared', false)
                        ->get();
                    if ($recoveredVendChannelErrorLogs) {
                        foreach ($recoveredVendChannelErrorLogs as $recoveredVendChannelErrorLog) {
                            $recoveredVendChannelErrorLog->is_error_cleared = true;
                            $recoveredVendChannelErrorLog->save();

                            if (Schema::hasTable('vend_logs')) {
                                VendLog::create([
                                    'vend_id' => $vend->id,
                                    'event' => 'machine_health_alert_dismissed',
                                    'subject' => sprintf('Channel %s error %s cleared', $recoveredVendChannelErrorLog->vendChannel->code, $recoveredVendChannelErrorLog->vendChannelError->code),
                                    'context' => [
                                        'channel_code' => $recoveredVendChannelErrorLog->vendChannel->code,
                                        'error_code' => $recoveredVendChannelErrorLog->vendChannelError->code,
                                    ],
                                    'occurred_at' => now(),
                                ]);
                            }
                        }
                    }
                }

            }
            SaveVendChannelErrorLogsJson::dispatch($vend->id)->onQueue('default');
        }
    }
}
