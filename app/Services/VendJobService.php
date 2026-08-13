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
}
