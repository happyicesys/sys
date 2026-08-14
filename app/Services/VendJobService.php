<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use App\Models\VendJob;

class VendJobService
{
    /**
     * Create a new VendJob and dispatch it via MQTT.
     *
     * @param  int|Vend  $vend
     * @param  callable|null  $formatter  Function to format the final MQTT message: function($payload, $vend)
     */
    public function dispatch($vend, string $type, array $payload = [], ?callable $formatter = null): ?VendJob
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::find($vend);
        $vendId = $vendModel->id;
        $vendCode = $vendModel->code;

        // BETA TESTING CHECK: Only execute VendJob logic for code 2007
        if ((string) $vendCode !== '2007') {
            if ($formatter) {
                $message = $formatter($payload, $vendModel);
            } else {
                $message = json_encode($payload);
            }
            PublishMqtt::dispatch('CM'.$vendCode, $message, 0)->onQueue('default');

            return null;
        }

        // 1. Create the job record first to get the ID
        $vendJob = VendJob::create([
            'vend_id' => $vendId,
            'type' => $type,
            'payload' => json_encode($payload), // Initial payload must be stringified since we removed model casting
            'is_returned' => false,
            'retries_count' => 0,
        ]);

        // 2. Inject the vend_job_id into the payload
        $payload['vend_job_id'] = $vendJob->id;

        // 3. Determine the final message and stored payload
        if ($formatter) {
            $message = $formatter($payload, $vendModel);
            $storedPayload = $message;
        } else {
            $message = json_encode($payload);
            $storedPayload = $message; // Store the JSON string
        }

        // 4. Update the job with the final payload
        $vendJob->update(['payload' => $storedPayload]);

        // 5. Dispatch the MQTT message
        PublishMqtt::dispatch('CM'.$vendCode, $message, 0)->onQueue('default');

        return $vendJob;
    }

    /**
     * Canonical "re-read your settings now" nudge (TYPESYNCSETTINGSPARAM).
     *
     * The payload carries NO settings values — it tells the machine to re-fetch
     * /api/vends/{code}/parameters and to re-sync its banner/campaign media. So
     * it is always safe to send late or twice: the machine reads whatever is
     * current at the moment it fetches.
     *
     * Lives here rather than in a controller because several callers need it
     * (the manual Push button, the auto-push on save/bind/media change, and the
     * queued fan-out job).
     *
     * @param  int|Vend  $vend
     */
    public function syncSettingsToVend($vend): ?VendJob
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::find($vend);

        if (! $vendModel) {
            return null;
        }

        $payload = [
            'Type' => 'TYPESYNCSETTINGSPARAM',
            'time' => now()->timestamp,
            'action' => '',
            'mid' => $vendModel->code,
        ];

        return $this->dispatch($vendModel, 'TYPESYNCSETTINGSPARAM', $payload, function ($payload, $vend) {
            $fid = 1;
            $content = base64_encode(json_encode($payload));
            $contentLength = strlen($content);
            $key = $vend && $vend->private_key ? $vend->private_key : config('vend.private_key');
            $md5 = md5($fid.','.$contentLength.','.$content.$key);

            return $fid.','.$contentLength.','.$content.','.$md5;
        });
    }

    /**
     * Canonical "re-fetch your menu now" nudge (TYPESYNCAPICHANNELSLOTLIST).
     *
     * Same contract as syncSettingsToVend above: the payload carries NO channel
     * data — it tells the terminal to re-fetch /api/vends/{code}/thumbnails and
     * re-download channel images, so it reads whatever is current at the moment
     * it fetches and is always safe to send late or twice. Callers must commit
     * the vend_channels write (ProductMappingService::syncChannels) BEFORE
     * sending, or the machine re-fetches the old menu.
     *
     * Only the vending-machine boards (mark1-apk / mark1-apk-small) understand
     * this frame, so other machine types are skipped here — smart freezers get
     * their own nudge via SmartFreezerCatalogPush, and Citybox chillers have no
     * APK of ours. Gated in ONE place so no caller has to remember.
     *
     * Deliberately does NOT go through dispatch() / create a VendJob row, unlike
     * syncSettingsToVend. VendJob tracking only works for frames the terminal
     * acks: SyncJobApkSetting flips is_returned when the APK replies JOBAPKSETTING
     * to a settings push, and that is the ONLY ack path. The APK answers this
     * frame with no reply at all (CvMqttService just calls
     * OnSyncApiChannelSlotList()), so a row here could never be marked returned
     * and vend:retry-jobs would re-publish it every 5 minutes for an hour —
     * a dozen forced menu re-fetches and image re-downloads per rebind.
     *
     * Published at QoS 1 on the `high` queue, matching what the three call sites
     * did inline before they were consolidated here: a menu change is exactly
     * the case where at-least-once delivery matters.
     *
     * @param  int|Vend  $vend
     * @return bool true when a nudge was published
     */
    public function syncChannelSlotListToVend($vend): bool
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::withoutGlobalScopes()->find($vend);

        if (! $vendModel) {
            return false;
        }

        if (($vendModel->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE) !== Vend::MACHINE_TYPE_VENDING_MACHINE) {
            return false;
        }

        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'TYPESYNCAPICHANNELSLOTLIST',
            'time' => now()->timestamp,
            'action' => '',
            'mid' => $vendModel->code,
        ]));
        $contentLength = strlen($content);
        $key = $vendModel->private_key ?: config('vend.private_key');
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vendModel->code, $fid.','.$contentLength.','.$content.','.$md5, 1)
            ->onQueue('high');

        return true;
    }
}
