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

class SyncVendChannelErrorLog implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 60;

    public function uniqueId()
    {
        return $this->vend->id . '_' . $this->vendChannelCode . '_' . $this->vendChannelErrorCode . '_' . ($this->vendTransactionId ?? 'null');
    }

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

                // If transaction ID is provided, check if THIS specific transaction already has a log for this error
                if ($vendTransactionId) {
                    $exists = VendChannelErrorLog::where('vend_transaction_id', $vendTransactionId)
                        ->where('vend_channel_id', $vendChannel->id)
                        ->where('vend_channel_error_id', $vendChannelError->id)
                        ->exists();

                    if ($exists) {
                        return; // Already logged for this transaction
                    }
                    $vendChannelErrorLog = VendChannelErrorLog::create([
                        'vend_channel_id' => $vendChannel->id,
                        'vend_channel_error_id' => $vendChannelError->id,
                        'vend_transaction_id' => $vendTransactionId
                    ]);
                } else {
                    // Heartbeat/Sync: Only create if no active log exists for this channel/error to prevent spam
                    $activeLog = $vendChannel->vendChannelErrorLogs()
                        ->where('is_error_cleared', false)
                        ->where('vend_channel_error_id', $vendChannelError->id)
                        ->first();

                    if ($activeLog) {
                        return; // Machine is already in this error state, no need for redundant badge
                    }

                    $vendChannelErrorLog = VendChannelErrorLog::create([
                        'vend_channel_id' => $vendChannel->id,
                        'vend_channel_error_id' => $vendChannelError->id
                    ]);
                }

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

                    \App\Models\MachineHealthHistory::log(
                        $vend->id,
                        'machine_health_alert',
                        'channel_error',
                        'channel_error',
                        null,
                        [
                            'vend_channel_error_log_id' => $vendChannelErrorLog->id,
                            'channel_code' => $vendChannelCode,
                            'error_code' => $vendChannelErrorCode,
                        ],
                        now()
                    );
                } catch (\Throwable $e) {
                    Logger::warning('Failed to create vend log for channel error', [
                        'vend_id' => $vend->id,
                        'channel_code' => $vendChannelCode,
                        'error_code' => $vendChannelErrorCode,
                        'message' => $e->getMessage(),
                    ]);
                }

                $lastLog = $vendChannel->vendChannelErrorLogs()
                    ->where('id', '!=', $vendChannelErrorLog->id)
                    ->latest()
                    ->first();

                if ($lastLog and $lastLog->vendChannelError->code != $vendChannelErrorCode and !$lastLog->is_error_cleared) {
                    $lastLog->is_error_cleared = true;
                    $lastLog->save();

                    \App\Models\MachineHealthHistory::log(
                        $vend->id,
                        'machine_health_alert_dismissed',
                        'channel_error',
                        'channel_error',
                        null,
                        [
                            'channel_code' => $lastLog->vendChannel->code,
                            'error_code' => $lastLog->vendChannelError->code,
                        ],
                        now()
                    );
                }

            } else {
                $recoveredChannel = VendChannel::where('vend_id', $vend->id)->where('code', $vendChannelCode)->first();
                if ($recoveredChannel) {
                    $recoveredVendChannelErrorLogs = VendChannelErrorLog::where('vend_channel_id', $recoveredChannel->id)
                        ->where('is_error_cleared', false)
                        ->get();
                    if ($recoveredVendChannelErrorLogs) {
                        /** @var VendChannelErrorLog \$recoveredVendChannelErrorLog */
                        foreach ($recoveredVendChannelErrorLogs as $recoveredVendChannelErrorLog) {
                            $recoveredVendChannelErrorLog->is_error_cleared = true;
                            $recoveredVendChannelErrorLog->save();

                            \App\Models\MachineHealthHistory::log(
                                $vend->id,
                                'machine_health_alert_dismissed',
                                'channel_error',
                                'channel_error',
                                null,
                                [
                                    'channel_code' => $recoveredVendChannelErrorLog->vendChannel->code,
                                    'error_code' => $recoveredVendChannelErrorLog->vendChannelError->code,
                                ],
                                now()
                            );
                        }
                    }
                }

            }
            SaveVendChannelErrorLogsJson::dispatch($vend->id)->onQueue('default');
        }
    }
}
